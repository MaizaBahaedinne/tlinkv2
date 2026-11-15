<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\ClubActivity;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\Challenges\ActivityRegistrationQuestions;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Formulaire d'activité en étapes : comité d'organisation et questions complémentaires aux participants. */
class ActivityCommitteeTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private OrgNode $club;

    private OrgNode $otherClub;

    private User $president;

    private User $salma;

    private User $karim;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernorateSeeder::class);
        app(OrgChartInstaller::class)->install();
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $governorate = Governorate::query()->first();
        $this->club = app(OrgChartInstaller::class)->createClub($branch, 'ESPRIT', 'university_club', $governorate, true);
        $this->otherClub = app(OrgChartInstaller::class)->createClub($branch, 'ENIT', 'university_club', $governorate, true);

        $this->president = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $this->club->title)->where('role_key', 'president')->firstOrFail(), $this->president);

        $this->salma = User::factory()->create(['name' => 'Salma Ben Ali']);
        $this->karim = User::factory()->create(['name' => 'Karim Trabelsi']);
        $this->stranger = User::factory()->create(['name' => 'Intrus ENIT']);
        Membership::query()->create(['user_id' => $this->salma->id, 'scope' => 'university', 'node_id' => $this->club->id, 'status' => 'approved', 'membership_type' => 'member']);
        Membership::query()->create(['user_id' => $this->karim->id, 'scope' => 'university', 'node_id' => $this->club->id, 'status' => 'approved', 'membership_type' => 'member']);
        Membership::query()->create(['user_id' => $this->stranger->id, 'scope' => 'university', 'node_id' => $this->otherClub->id, 'status' => 'approved', 'membership_type' => 'member']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'club_id' => $this->club->id, 'title' => 'Camp d’intégration', 'type' => 'camping', 'audience' => 'public', 'status' => 'published',
            'starts_at' => now()->addDays(40)->format('Y-m-d\TH:i'),
        ];
    }

    public function test_the_form_is_a_four_step_wizard_with_a_committee_step_and_suggested_questions(): void
    {
        $this->actingAs($this->president)->get('/admin/activites/creer')->assertOk()
            ->assertSee('Comité d’organisation')->assertSee('Inscription des participants')->assertSee('wizard-submit', false)
            ->assertSee('Taille de t-shirt')->assertSee('Moyen de transport');
    }

    public function test_every_activity_type_has_suggested_questions(): void
    {
        foreach (array_keys(ClubActivity::TYPES) as $type) {
            $questions = ActivityRegistrationQuestions::all()[$type];

            $this->assertNotEmpty($questions, $type);

            foreach ($questions as $question) {
                $this->assertArrayHasKey($question['type'], ClubActivity::FIELD_TYPES);
                $this->assertTrue($question['type'] !== 'choice' || count($question['options']) >= 2, $question['label']);
            }
        }
    }

    public function test_the_committee_list_only_offers_people_of_the_chosen_club(): void
    {
        $response = $this->actingAs($this->president)->getJson('/admin/activites/comite?club='.$this->club->id)->assertOk();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Salma Ben Ali'));
        $this->assertTrue($names->contains('Karim Trabelsi'));
        $this->assertFalse($names->contains('Intrus ENIT'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($this->president)->getJson('/admin/activites/comite?club='.$this->otherClub->id)->assertForbidden();
        $this->actingAs($this->president)->getJson('/admin/activites/comite')->assertForbidden();
        $this->actingAs($this->salma)->getJson('/admin/activites/comite?club='.$this->club->id)->assertForbidden();
    }

    public function test_the_committee_is_chosen_at_creation_and_members_are_notified(): void
    {
        $this->actingAs($this->president)->post('/admin/activites', $this->payload([
            'committee_ids' => [$this->salma->id, $this->karim->id, $this->president->id],
            'committee_roles' => [$this->salma->id => 'lead', $this->karim->id => 'sponsoring', $this->president->id => 'member'],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $activity = ClubActivity::query()->where('title', 'Camp d’intégration')->firstOrFail();

        $this->assertSame(
            [$this->salma->id => 'lead', $this->karim->id => 'sponsoring', $this->president->id => 'member'],
            $activity->committee()->pluck('role', 'user_id')->all(),
        );

        $this->assertSame(1, AppNotification::query()->where('user_id', $this->salma->id)->where('type', 'activity.committee')->count());
        $this->assertSame(0, AppNotification::query()->where('user_id', $this->president->id)->where('type', 'activity.committee')->count());

        $this->actingAs($this->president)->get("/admin/activites/{$activity->id}")->assertOk()->assertSee('Comité d’organisation')->assertSee('Salma Ben Ali')->assertSee('Coordinateur(trice)')->assertSee('Équipe sponsoring');
    }

    public function test_people_outside_the_club_cannot_join_the_committee(): void
    {
        $this->actingAs($this->president)->post('/admin/activites', $this->payload(['committee_ids' => [$this->stranger->id]]))->assertSessionHasErrors('committee_ids');
        $this->assertSame(0, ClubActivity::query()->count());

        $this->actingAs($this->president)->post('/admin/activites', $this->payload(['committee_ids' => [$this->salma->id], 'committee_roles' => [$this->salma->id => 'boss']]))->assertSessionHasErrors('committee_roles.*');
    }

    public function test_the_committee_can_be_changed_later_and_only_new_members_are_notified(): void
    {
        $this->actingAs($this->president)->post('/admin/activites', $this->payload(['committee_ids' => [$this->salma->id], 'committee_roles' => [$this->salma->id => 'lead']]));
        $activity = ClubActivity::query()->firstOrFail();

        $this->actingAs($this->president)->get("/admin/activites/{$activity->id}/modifier")->assertOk()->assertSee('Salma Ben Ali');

        $this->actingAs($this->president)->put("/admin/activites/{$activity->id}", $this->payload([
            'committee_ids' => [$this->salma->id, $this->karim->id], 'committee_roles' => [$this->salma->id => 'lead', $this->karim->id => 'hr'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, $activity->committee()->count());
        $this->assertSame(1, AppNotification::query()->where('user_id', $this->salma->id)->where('type', 'activity.committee')->count());
        $this->assertSame(1, AppNotification::query()->where('user_id', $this->karim->id)->where('type', 'activity.committee')->count());

        // Un envoi sans cocher personne vide le comité.
        $this->actingAs($this->president)->put("/admin/activites/{$activity->id}", $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(0, $activity->committee()->count());
    }

    public function test_registration_questions_are_still_saved_and_asked_to_participants(): void
    {
        $this->actingAs($this->president)->post('/admin/activites', $this->payload(['fields' => [
            ['label' => 'Taille de t-shirt', 'type' => 'choice', 'options' => 'S, M, L', 'required' => 1],
            ['label' => '', 'type' => 'text'],
        ]]))->assertSessionHasNoErrors();

        $activity = ClubActivity::query()->firstOrFail();
        $this->assertSame('Taille de t-shirt', $activity->registration_fields[0]['label']);
        $this->assertSame(['S', 'M', 'L'], $activity->registration_fields[0]['options']);
        $this->assertCount(1, $activity->registration_fields);

        $this->actingAs($this->salma)->get("/activites/{$activity->id}")->assertOk()->assertSee('Taille de t-shirt');
    }

    public function test_deleting_an_activity_removes_its_committee(): void
    {
        $this->actingAs($this->president)->post('/admin/activites', $this->payload(['committee_ids' => [$this->salma->id]]));
        $activity = ClubActivity::query()->firstOrFail();

        $this->actingAs($this->president)->delete("/admin/activites/{$activity->id}")->assertRedirect('/admin/activites');
        $this->assertSame(0, \App\Models\ActivityCommitteeMember::query()->count());
    }
}
