<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Governorate;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Réunions : planification, ordre du jour, invitations, réponses, présences et décisions par point. */
class MeetingTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private OrgNode $club;

    private OrgNode $otherClub;

    private User $admin;

    private User $president;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernorateSeeder::class);
        app(OrgChartInstaller::class)->install();
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $governorate = Governorate::query()->first();
        $this->club = app(OrgChartInstaller::class)->createClub($branch, 'ESPRIT', 'university_club', $governorate, true);
        $this->otherClub = app(OrgChartInstaller::class)->createClub($branch, 'ENIT', 'university_club', $governorate, true);

        $this->admin = User::factory()->create();
        $this->grantPlatformAdmin($this->admin);

        $this->president = User::factory()->create(['name' => 'Président Esprit']);
        $this->placeOn(OrgNode::query()->where('subtitle', $this->club->title)->where('role_key', 'president')->firstOrFail(), $this->president);

        $this->member = User::factory()->create(['name' => 'Salma Ben Ali']);
        Membership::query()->create(['user_id' => $this->member->id, 'scope' => 'university', 'node_id' => $this->club->id, 'status' => 'approved', 'membership_type' => 'member']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'club_id' => $this->club->id, 'title' => 'Bureau mensuel', 'subject' => 'Préparer le gala', 'starts_at' => now()->addDays(5)->setTime(18, 30)->format('Y-m-d\TH:i'),
            'duration_minutes' => 90, 'location' => 'Salle B12',
        ];
    }

    private function createMeeting(): Meeting
    {
        $this->actingAs($this->president)->post('/admin/reunions', $this->payload())->assertRedirect();

        return Meeting::query()->firstOrFail();
    }

    public function test_a_club_manager_creates_a_meeting_and_is_its_first_attendee(): void
    {
        $meeting = $this->createMeeting();

        $this->assertSame($this->president->id, $meeting->organizer_id);
        $this->assertSame(90, $meeting->duration_minutes);
        $this->assertSame([$this->president->id], $meeting->attendees()->pluck('user_id')->all());
        $this->assertTrue(AuditLog::query()->where('action', 'admin.meeting.created')->exists());

        $this->actingAs($this->president)->get("/admin/reunions/{$meeting->id}")->assertOk()->assertSee('Bureau mensuel')->assertSee('Salle B12');
        $this->actingAs($this->president)->get('/admin/reunions')->assertOk()->assertSee('Bureau mensuel');
    }

    public function test_meeting_validation_and_club_scope(): void
    {
        $this->actingAs($this->president)->post('/admin/reunions', $this->payload(['title' => '', 'duration_minutes' => 1, 'online_url' => 'ftp://x']))
            ->assertSessionHasErrors(['title', 'duration_minutes', 'online_url']);
        $this->actingAs($this->president)->post('/admin/reunions', $this->payload(['club_id' => $this->otherClub->id]))->assertSessionHasErrors('club_id');
        $this->actingAs($this->president)->post('/admin/reunions', $this->payload(['club_id' => null]))->assertSessionHasErrors('club_id');

        // Seul l'administrateur global peut convoquer une réunion de la Fondation (sans club).
        $this->actingAs($this->admin)->post('/admin/reunions', $this->payload(['club_id' => null, 'title' => 'AG de la Fondation']))->assertRedirect();
        $this->assertNull(Meeting::query()->where('title', 'AG de la Fondation')->firstOrFail()->club_id);
    }

    public function test_foreign_meetings_are_invisible_and_forbidden(): void
    {
        $foreign = Meeting::query()->create(['club_id' => $this->otherClub->id, 'title' => 'Réunion ENIT', 'starts_at' => now()->addDay(), 'duration_minutes' => 60, 'organizer_id' => $this->admin->id, 'status' => 'planned']);

        $this->actingAs($this->president)->get('/admin/reunions')->assertOk()->assertDontSee('Réunion ENIT');
        $this->actingAs($this->president)->get("/admin/reunions/{$foreign->id}")->assertForbidden();
        $this->actingAs($this->president)->delete("/admin/reunions/{$foreign->id}")->assertForbidden();
        $this->actingAs($this->president)->post("/admin/reunions/{$foreign->id}/points", ['title' => 'Intrus'])->assertForbidden();
        $this->actingAs($this->president)->post("/admin/reunions/{$foreign->id}/invitations")->assertForbidden();
    }

    public function test_agenda_items_can_be_added_ordered_decided_and_removed(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/points", ['title' => 'Budget du gala', 'duration_minutes' => 20])->assertRedirect();
        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/points", ['title' => 'Choix du traiteur', 'duration_minutes' => 15])->assertRedirect();

        [$first, $second] = $meeting->items()->get()->all();
        $this->assertSame(['Budget du gala', 'Choix du traiteur'], $meeting->items()->pluck('title')->all());

        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/points/{$second->id}/deplacer", ['direction' => 'up'])->assertRedirect();
        $this->assertSame(['Choix du traiteur', 'Budget du gala'], $meeting->items()->pluck('title')->all());

        $this->actingAs($this->president)->put("/admin/reunions/{$meeting->id}/points/{$first->id}", [
            'title' => 'Budget du gala', 'duration_minutes' => 20, 'presenter_id' => $this->president->id, 'outcome' => 'adopted', 'decision' => 'Budget de 8 000 DT adopté, trésorier chargé du suivi.',
        ])->assertRedirect();

        $first->refresh();
        $this->assertSame('adopted', $first->outcome);
        $this->assertSame('Budget de 8 000 DT adopté, trésorier chargé du suivi.', $first->decision);
        $this->assertSame($this->president->id, $first->presenter_id);

        $this->actingAs($this->president)->get("/admin/reunions/{$meeting->id}")->assertOk()->assertSee('Budget de 8 000 DT adopté');

        $this->actingAs($this->president)->put("/admin/reunions/{$meeting->id}/points/{$first->id}", ['title' => 'Budget du gala', 'outcome' => 'bizarre'])->assertSessionHasErrors('outcome');

        $this->actingAs($this->president)->delete("/admin/reunions/{$meeting->id}/points/{$second->id}")->assertRedirect();
        $this->assertSame(1, $meeting->items()->count());
    }

    public function test_an_agenda_item_of_another_meeting_cannot_be_touched(): void
    {
        $meeting = $this->createMeeting();
        $other = Meeting::query()->create(['club_id' => $this->club->id, 'title' => 'Autre', 'starts_at' => now()->addDay(), 'duration_minutes' => 30, 'organizer_id' => $this->president->id, 'status' => 'planned']);
        $item = MeetingAgendaItem::query()->create(['meeting_id' => $other->id, 'title' => 'Point', 'position' => 1]);

        $this->actingAs($this->president)->put("/admin/reunions/{$meeting->id}/points/{$item->id}", ['title' => 'Détourné'])->assertNotFound();
        $this->actingAs($this->president)->delete("/admin/reunions/{$meeting->id}/points/{$item->id}")->assertNotFound();
    }

    public function test_attendees_are_invited_notified_once_and_can_respond(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/invites", ['group' => 'members'])->assertRedirect();
        $this->assertTrue($meeting->attendees()->where('user_id', $this->member->id)->exists());
        $this->assertSame(0, AppNotification::query()->where('user_id', $this->member->id)->count());

        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/invitations")->assertRedirect();
        $this->assertSame(1, AppNotification::query()->where('user_id', $this->member->id)->where('type', 'meeting.invited')->count());
        $this->assertSame(0, AppNotification::query()->where('user_id', $this->president->id)->count());

        // Un second envoi ne prévient personne de nouveau.
        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/invitations")->assertRedirect();
        $this->assertSame(1, AppNotification::query()->where('user_id', $this->member->id)->where('type', 'meeting.invited')->count());

        $this->actingAs($this->member)->get('/mes-reunions')->assertOk()->assertSee('Bureau mensuel');
        $this->actingAs($this->member)->post("/mes-reunions/{$meeting->id}/reponse", ['response' => 'declined'])->assertRedirect();

        $this->assertSame('declined', $meeting->attendees()->where('user_id', $this->member->id)->firstOrFail()->response);
        $this->assertSame(1, AppNotification::query()->where('user_id', $this->president->id)->where('type', 'meeting.response')->count());
    }

    public function test_only_invited_users_can_respond(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->member)->post("/mes-reunions/{$meeting->id}/reponse", ['response' => 'accepted'])->assertNotFound();
        $this->actingAs($this->member)->get('/mes-reunions')->assertOk()->assertDontSee('Bureau mensuel');
    }

    public function test_attendance_is_recorded_after_the_meeting(): void
    {
        $meeting = $this->createMeeting();
        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/invites", ['user_ids' => [$this->member->id]]);

        $this->actingAs($this->president)->put("/admin/reunions/{$meeting->id}/presences", ['attendance' => [$this->member->id => 'excused', $this->president->id => 'present']])->assertRedirect();
        $this->assertSame('excused', $meeting->attendees()->where('user_id', $this->member->id)->firstOrFail()->attendance);
        $this->assertSame('present', $meeting->attendees()->where('user_id', $this->president->id)->firstOrFail()->attendance);

        $this->actingAs($this->president)->put("/admin/reunions/{$meeting->id}/presences", ['attendance' => [$this->member->id => 'inconnu']])->assertSessionHasErrors('attendance.*');
    }

    public function test_moving_a_sent_meeting_notifies_guests_and_cancelling_too(): void
    {
        $meeting = $this->createMeeting();
        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/invites", ['user_ids' => [$this->member->id]]);
        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/invitations");

        $this->actingAs($this->president)->put("/admin/reunions/{$meeting->id}", $this->payload(['location' => 'Amphi A']))->assertRedirect();
        $this->assertSame(1, AppNotification::query()->where('user_id', $this->member->id)->where('type', 'meeting.updated')->count());

        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/statut", ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(1, AppNotification::query()->where('user_id', $this->member->id)->where('type', 'meeting.cancelled')->count());
        $this->assertSame('cancelled', $meeting->fresh()->status);

        // Une réunion annulée ne se renvoie plus.
        $this->actingAs($this->president)->post("/admin/reunions/{$meeting->id}/invitations")->assertStatus(422);
    }

    public function test_minutes_and_deletion_are_audited(): void
    {
        $meeting = $this->createMeeting();

        $this->actingAs($this->president)->put("/admin/reunions/{$meeting->id}/compte-rendu", ['minutes' => 'Séance ouverte à 18h30.'])->assertRedirect();
        $this->assertSame('Séance ouverte à 18h30.', $meeting->fresh()->minutes);

        $this->actingAs($this->president)->delete("/admin/reunions/{$meeting->id}")->assertRedirect('/admin/reunions');
        $this->assertSame(0, Meeting::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'admin.meeting.deleted')->exists());
    }

    public function test_a_user_without_rights_cannot_reach_the_admin_pages(): void
    {
        $this->actingAs($this->member)->get('/admin/reunions')->assertForbidden();
    }
}
