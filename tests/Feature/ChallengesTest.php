<?php

namespace Tests\Feature;

use App\Models\ActivityReport;
use App\Models\ActivityScore;
use App\Models\AppModule;
use App\Models\Challenge;
use App\Models\ClubActivity;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\Challenges\Challenges;
use App\Support\Challenges\ChallengeScoring;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Défis thématiques, activités des clubs (co-organisation, inscription, QR code) et notation par un comité. */
class ChallengesTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function club(string $name, bool $template = false): OrgNode
    {
        $this->seed(GovernorateSeeder::class);

        if (! OrgNode::query()->where('system_key', 'university-clubs')->exists()) {
            app(OrgChartInstaller::class)->install();
        }

        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();

        return app(OrgChartInstaller::class)->createClub($branch, $name, 'university_club', Governorate::query()->first(), $template);
    }

    private function committeeMember(): User
    {
        return User::factory()->create();
    }

    /** Défi publié (en cours) avec un barème thématique, un barème hors thématique et un comité. */
    private function challenge(?User $committee = null, array $attributes = []): Challenge
    {
        $challenge = Challenge::query()->create($attributes + [
            'title' => 'Défi Environnement', 'theme' => 'Sensibilisation écologique', 'scope' => 'university',
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(10), 'evaluation_at' => now()->addDays(12), 'results_at' => now()->addDays(14),
            'status' => 'published',
        ]);

        $challenge->criteria()->createMany([
            ['kind' => 'theme', 'label' => 'Impact', 'max_points' => 10, 'sort_order' => 0],
            ['kind' => 'theme', 'label' => 'Qualité', 'max_points' => 5, 'sort_order' => 1],
            ['kind' => 'off_theme', 'label' => 'Initiative', 'max_points' => 5, 'sort_order' => 0],
        ]);
        $challenge->committee()->attach(($committee ?? $this->committeeMember())->id);

        return $challenge;
    }

    private function activity(Challenge $challenge, OrgNode $club, array $attributes = []): ClubActivity
    {
        return ClubActivity::query()->create($attributes + [
            'challenge_id' => $challenge->id, 'club_id' => $club->id, 'title' => 'Nettoyage de plage', 'type' => 'event', 'audience' => 'public',
            'in_theme' => true, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(3), 'status' => 'published',
        ]);
    }

    private function validatedReport(ClubActivity $activity): ActivityReport
    {
        return ActivityReport::query()->create(['activity_id' => $activity->id, 'status' => 'validated', 'summary' => 'Bilan', 'budget_spent' => 0, 'validated_at' => now()]);
    }

    private function approvedMember(User $user, OrgNode $node): void
    {
        Membership::query()->create(['user_id' => $user->id, 'scope' => 'university', 'node_id' => $node->id, 'status' => 'approved', 'membership_type' => 'member']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Défi Climat', 'theme' => 'Eau et climat', 'description' => 'Consignes', 'scope' => 'university', 'status' => 'draft',
            'starts_at' => now()->addDays(1)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(20)->format('Y-m-d\TH:i'),
            'evaluation_at' => now()->addDays(22)->format('Y-m-d\TH:i'), 'results_at' => now()->addDays(25)->format('Y-m-d\TH:i'),
            'criteria' => ['theme' => [['label' => 'Impact', 'max_points' => 10], ['label' => 'Originalité', 'max_points' => 5]], 'off_theme' => [['label' => 'Initiative', 'max_points' => 4]]],
            'committee_emails' => '',
        ];
    }

    public function test_admin_creates_a_challenge_with_scales_and_committee(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $juror = User::factory()->create(['email' => 'jury@example.test']);

        $this->actingAs($admin)->get('/admin/defis/creer')->assertOk()->assertSee('Nouveau défi');

        $this->actingAs($admin)->post('/admin/defis', $this->payload(['status' => 'published', 'committee_emails' => 'jury@example.test', 'invited_scopes' => ['university', 'high-school']]))
            ->assertRedirect();

        $challenge = Challenge::query()->firstOrFail();
        $this->assertSame('Eau et climat', $challenge->theme);
        $this->assertSame(['university', 'high-school'], $challenge->invited_scopes);
        $this->assertSame([10, 5], $challenge->criteriaOf('theme')->pluck('max_points')->all());
        $this->assertSame([4], $challenge->criteriaOf('off_theme')->pluck('max_points')->all());
        $this->assertTrue($challenge->isCommitteeMember($juror));

        $this->actingAs($admin)->get('/admin/defis')->assertOk()->assertSee('Défi Climat');
        $this->actingAs($admin)->get('/admin/defis/'.$challenge->id)->assertOk()->assertSee('Eau et climat')->assertSee('Originalité');
        $this->actingAs($admin)->get('/admin/defis/'.$challenge->id.'/modifier')->assertOk()->assertSee('Originalité');
    }

    public function test_challenge_calendar_and_publication_rules_are_enforced(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->post('/admin/defis', $this->payload(['ends_at' => now()->format('Y-m-d\TH:i'), 'starts_at' => now()->addDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('ends_at');
        $this->actingAs($admin)->post('/admin/defis', $this->payload(['results_at' => now()->addDays(21)->format('Y-m-d\TH:i')]))->assertSessionHasErrors('results_at');
        $this->actingAs($admin)->post('/admin/defis', $this->payload(['status' => 'published']))->assertSessionHasErrors('committee_emails');
        $this->actingAs($admin)->post('/admin/defis', $this->payload(['status' => 'published', 'committee_emails' => 'inconnu@example.test']))->assertSessionHasErrors('committee_emails');
        $this->actingAs($admin)->post('/admin/defis', $this->payload(['criteria' => ['theme' => [['label' => 'Impact', 'max_points' => 0]]]]))->assertSessionHasErrors('criteria.theme');

        $juror = User::factory()->create(['email' => 'jury@example.test']);
        $this->actingAs($admin)->post('/admin/defis', $this->payload(['status' => 'published', 'committee_emails' => $juror->email, 'criteria' => ['theme' => []]]))->assertSessionHasErrors('criteria.theme');

        $this->assertSame(0, Challenge::query()->count());
    }

    public function test_scale_structure_is_frozen_once_scores_exist(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $juror = $this->committeeMember();
        $challenge = $this->challenge($juror);
        $activity = $this->activity($challenge, $this->club('ESPRIT'));
        $impact = $challenge->criteriaOf('theme')->first();
        ActivityScore::query()->create(['activity_id' => $activity->id, 'criterion_id' => $impact->id, 'evaluator_id' => $juror->id, 'points' => 7]);

        $payload = $this->payload(['status' => 'published', 'committee_emails' => $juror->email, 'criteria' => ['theme' => [['id' => $impact->id, 'label' => 'Impact renommé', 'max_points' => 99]]]]);
        $this->actingAs($admin)->put('/admin/defis/'.$challenge->id, $payload)->assertRedirect();

        $impact->refresh();
        $this->assertSame('Impact renommé', $impact->label);
        $this->assertSame(10, $impact->max_points);
        $this->assertSame(2, $challenge->criteriaOf('theme')->count());
    }

    public function test_club_manager_proposes_activities_only_for_their_club_and_inside_the_challenge(): void
    {
        $club = $this->club('ESPRIT', true);
        $other = $this->club('ISET');
        $president = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'president')->firstOrFail(), $president);
        $challenge = $this->challenge();
        $challenge->criteria()->where('kind', 'off_theme')->delete();

        $base = ['challenge_id' => $challenge->id, 'club_id' => $club->id, 'title' => 'Conférence verte', 'type' => 'conference', 'audience' => 'public', 'in_theme' => 1, 'status' => 'published', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i')];

        $this->actingAs($president)->get('/admin/activites/creer')->assertOk()->assertSee('ESPRIT')->assertDontSee('ISET');
        $this->actingAs($president)->post('/admin/activites', $base)->assertRedirect();
        $this->assertSame(1, ClubActivity::query()->where('club_id', $club->id)->count());

        $this->actingAs($president)->post('/admin/activites', ['club_id' => $other->id] + $base)->assertSessionHasErrors('club_id');
        $this->actingAs($president)->post('/admin/activites', ['starts_at' => now()->addDays(30)->format('Y-m-d\TH:i')] + $base)->assertSessionHasErrors('starts_at');
        $this->actingAs($president)->post('/admin/activites', ['in_theme' => 0] + $base)->assertSessionHasErrors('in_theme');
        $this->actingAs($president)->post('/admin/activites', ['audience' => 'department'] + $base)->assertSessionHasErrors('department_id');
        $this->assertSame(1, ClubActivity::query()->count());

        // Défi terminé : plus d'activité possible.
        $challenge->update(['ends_at' => now()->subDay(), 'evaluation_at' => now()->addDay(), 'results_at' => now()->addDays(2)]);
        $this->actingAs($president)->post('/admin/activites', $base)->assertSessionHasErrors('challenge_id');
    }

    public function test_the_lead_club_deletes_an_activity_and_registrants_are_told(): void
    {
        $club = $this->club('ESPRIT', true);
        $rival = $this->club('ISET', true);
        [$president, $rivalPresident, $member] = [User::factory()->create(), User::factory()->create(), User::factory()->create()];
        $this->placeOn(OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'president')->firstOrFail(), $president);
        $this->placeOn(OrgNode::query()->where('subtitle', $rival->title)->where('role_key', 'president')->firstOrFail(), $rivalPresident);
        $challenge = $this->challenge();
        $activity = $this->activity($challenge, $club);
        $activity->registrations()->create(['user_id' => $member->id, 'status' => 'registered']);

        // Le bouton est proposé au club porteur, avec ses inscrits.
        $this->actingAs($president)->get('/admin/activites')->assertOk()->assertSee(route('admin.activities.destroy', $activity), false);
        $this->actingAs($president)->get('/admin/activites/'.$activity->id)->assertOk()->assertSee('Supprimer cette activité ?');

        // Un autre club ne supprime pas l'activité.
        $this->actingAs($rivalPresident)->delete('/admin/activites/'.$activity->id)->assertForbidden();
        $this->assertSame(1, ClubActivity::query()->count());

        $this->actingAs($president)->delete('/admin/activites/'.$activity->id)->assertRedirect(route('admin.activities.index'))->assertSessionHas('status');
        $this->assertSame(0, ClubActivity::query()->count());
        $this->assertSame(0, \App\Models\ActivityRegistration::query()->count());
        $this->assertTrue(\App\Models\AppNotification::query()->where('user_id', $member->id)->where('type', 'activity.deleted')->exists());
    }

    public function test_a_scored_activity_cannot_be_deleted(): void
    {
        $club = $this->club('ESPRIT', true);
        $president = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'president')->firstOrFail(), $president);
        $juror = $this->committeeMember();
        $challenge = $this->challenge($juror);
        $scored = $this->activity($challenge, $club);
        ActivityScore::query()->create(['activity_id' => $scored->id, 'criterion_id' => $challenge->criteria()->first()->id, 'evaluator_id' => $juror->id, 'points' => 5]);
        $reported = $this->activity($challenge, $club, ['title' => 'Avec compte rendu']);
        $this->validatedReport($reported);

        $this->actingAs($president)->delete('/admin/activites/'.$scored->id)->assertSessionHas('error');
        $this->actingAs($president)->delete('/admin/activites/'.$reported->id)->assertSessionHas('error');
        $this->assertSame(2, ClubActivity::query()->count());
    }

    public function test_club_not_invited_to_the_challenge_cannot_propose(): void
    {
        $club = $this->club('ESPRIT', true);
        $president = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'president')->firstOrFail(), $president);
        $challenge = $this->challenge(null, ['invited_scopes' => ['high-school'], 'scope' => 'high-school']);

        $this->actingAs($president)->post('/admin/activites', ['challenge_id' => $challenge->id, 'club_id' => $club->id, 'title' => 'X', 'type' => 'event', 'audience' => 'public', 'in_theme' => 1, 'status' => 'draft', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('club_id');
    }

    public function test_audience_decides_who_can_register(): void
    {
        $club = $this->club('ESPRIT', true);
        $challenge = $this->challenge();
        $departmentId = array_key_first(Challenges::departmentsByClub()[$club->id]);

        $stranger = User::factory()->create();
        $clubMember = User::factory()->create();
        $deptMember = User::factory()->create();
        $tunimateur = User::factory()->create();
        $this->approvedMember($clubMember, $club);
        $this->approvedMember($deptMember, OrgNode::query()->findOrFail($departmentId));
        $this->approvedMember($tunimateur, $this->club('ISET'));

        $public = $this->activity($challenge, $club, ['audience' => 'public']);
        $internal = $this->activity($challenge, $club, ['audience' => 'club', 'title' => 'Interne']);
        $reserved = $this->activity($challenge, $club, ['audience' => 'tunimateurs', 'title' => 'Tunimateurs']);
        $department = $this->activity($challenge, $club, ['audience' => 'department', 'department_id' => $departmentId, 'title' => 'Département']);

        $expected = [
            [$public, [$stranger, $clubMember, $deptMember, $tunimateur]],
            [$internal, [$clubMember, $deptMember]],
            [$reserved, [$clubMember, $deptMember, $tunimateur]],
            [$department, [$deptMember]],
        ];

        foreach ($expected as [$activity, $allowed]) {
            foreach ([$stranger, $clubMember, $deptMember, $tunimateur] as $user) {
                $this->actingAs($user)->post("/activites/{$activity->id}/inscription");
                $registered = $activity->registrations()->where('user_id', $user->id)->exists();
                $this->assertSame(in_array($user, $allowed, true), $registered, "{$activity->title} / {$user->id}");
            }
        }
    }

    public function test_registration_form_capacity_waitlist_and_cancellation(): void
    {
        $club = $this->club('ESPRIT');
        $challenge = $this->challenge();
        $activity = $this->activity($challenge, $club, ['capacity' => 1, 'registration_fields' => [
            ['label' => 'Taille de t-shirt', 'type' => 'choice', 'required' => true, 'options' => ['S', 'M', 'L']],
            ['label' => 'Allergies', 'type' => 'text', 'required' => false, 'options' => []],
            ['label' => 'J’accepte la charte', 'type' => 'checkbox', 'required' => true, 'options' => []],
        ]]);
        [$a, $b] = [User::factory()->create(), User::factory()->create()];

        $this->actingAs($a)->get("/activites/{$activity->id}")->assertOk()->assertSee('Taille de t-shirt')->assertSee('M’inscrire');
        $this->actingAs($a)->post("/activites/{$activity->id}/inscription", ['answers' => [1 => 'Aucune']])->assertSessionHasErrors(['answers.0', 'answers.2']);
        $this->actingAs($a)->post("/activites/{$activity->id}/inscription", ['answers' => [0 => 'XXL', 2 => '1']])->assertSessionHasErrors('answers.0');
        $this->assertSame(0, $activity->registrations()->count());

        $this->actingAs($a)->post("/activites/{$activity->id}/inscription", ['answers' => [0 => 'M', 1 => 'Arachides', 2 => '1']])->assertSessionHas('status');
        $this->actingAs($b)->post("/activites/{$activity->id}/inscription", ['answers' => [0 => 'L', 2 => '1']]);

        $first = $activity->registrations()->where('user_id', $a->id)->firstOrFail();
        $this->assertSame('registered', $first->status);
        $this->assertSame('M', $first->answers[0]['value']);
        $this->assertSame('Oui', $first->answers[2]['value']);
        $this->assertSame('waitlisted', $activity->registrations()->where('user_id', $b->id)->value('status'));

        $this->actingAs($a)->delete("/activites/{$activity->id}/inscription")->assertSessionHas('status');
        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame('registered', $activity->registrations()->where('user_id', $b->id)->value('status'));

        // Réinscription après annulation.
        $this->actingAs($a)->post("/activites/{$activity->id}/inscription", ['answers' => [0 => 'S', 2 => '1']]);
        $this->assertSame(1, $activity->registrations()->where('user_id', $a->id)->count());
    }

    public function test_registration_closes_and_drafts_are_hidden(): void
    {
        $club = $this->club('ESPRIT');
        $challenge = $this->challenge();
        $closed = $this->activity($challenge, $club, ['registration_ends_at' => now()->subHour()]);
        $draft = $this->activity($challenge, $club, ['status' => 'draft', 'title' => 'Brouillon secret']);
        $user = User::factory()->create();
        $this->approvedMember($user, $club);

        $this->actingAs($user)->post("/activites/{$closed->id}/inscription")->assertSessionHas('error');
        $this->assertSame(0, $closed->registrations()->count());
        $this->actingAs($user)->get("/activites/{$draft->id}")->assertNotFound();
        $this->actingAs($user)->get('/defis/'.$challenge->id)->assertOk()->assertDontSee('Brouillon secret');
    }

    public function test_qr_code_validates_presence_of_registered_members_during_the_activity(): void
    {
        $club = $this->club('ESPRIT');
        $challenge = $this->challenge();
        $activity = $this->activity($challenge, $club);
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $url = "/activites/{$activity->id}/presence/{$activity->checkin_token}";

        $this->actingAs($member)->post("/activites/{$activity->id}/inscription");
        $this->assertStringContainsString($url, $activity->checkinUrl());

        // Trop tôt : la fenêtre ouvre 1 h avant le début.
        $this->actingAs($member)->get($url)->assertRedirect("/activites/{$activity->id}")->assertSessionHas('error');
        $this->assertNull($activity->registrations()->first()->checked_in_at);

        Carbon::setTestNow($activity->starts_at->copy()->addMinutes(20));
        $this->actingAs($stranger)->get($url)->assertSessionHas('error');
        $this->assertSame(0, $activity->registrations()->whereNotNull('checked_in_at')->count());

        $this->actingAs($member)->get("/activites/{$activity->id}/presence/mauvais-jeton")->assertNotFound();
        $this->actingAs($member)->get($url)->assertOk()->assertSee('Votre avis')->assertSee('valide votre présence');
        $this->assertNull($activity->registrations()->first()->checked_in_at);

        // Le formulaire exige les trois notes de 1 à 5.
        $this->actingAs($member)->post($url, ['feedback_overall' => 5])->assertSessionHasErrors(['feedback_organization', 'feedback_content']);
        $this->actingAs($member)->post($url, ['feedback_overall' => 6, 'feedback_organization' => 4, 'feedback_content' => 4])->assertSessionHasErrors('feedback_overall');
        $this->assertNull($activity->registrations()->first()->checked_in_at);

        $this->actingAs($stranger)->post($url, ['feedback_overall' => 5, 'feedback_organization' => 5, 'feedback_content' => 5])->assertSessionHas('error');
        $this->assertSame(0, $activity->registrations()->whereNotNull('checked_in_at')->count());

        $this->actingAs($member)->post($url, ['feedback_overall' => 5, 'feedback_organization' => 4, 'feedback_content' => 3, 'feedback_comment' => 'Très bien organisé'])->assertRedirect("/activites/{$activity->id}")->assertSessionHas('status');
        $registration = $activity->registrations()->first();
        $this->assertNotNull($registration->checked_in_at);
        $this->assertSame([5, 4, 3, 'Très bien organisé'], [$registration->feedback_overall, $registration->feedback_organization, $registration->feedback_content, $registration->feedback_comment]);
        $this->actingAs($member)->get("/activites/{$activity->id}")->assertSee('Présence validée');

        // Un second scan n'écrase pas l'avis déjà donné.
        $this->actingAs($member)->get($url)->assertRedirect("/activites/{$activity->id}");
        $this->actingAs($member)->post($url, ['feedback_overall' => 1, 'feedback_organization' => 1, 'feedback_content' => 1])->assertRedirect("/activites/{$activity->id}");
        $this->assertSame(5, $registration->fresh()->feedback_overall);

        // Trop tard : 3 h après la fin.
        $late = User::factory()->create();
        Carbon::setTestNow($activity->starts_at->copy()->subMinutes(30));
        $this->actingAs($late)->post("/activites/{$activity->id}/inscription");
        Carbon::setTestNow($activity->endsAt()->copy()->addHours(4));
        $this->actingAs($late)->get($url)->assertSessionHas('error');
        $this->actingAs($late)->post($url, ['feedback_overall' => 5, 'feedback_organization' => 5, 'feedback_content' => 5])->assertSessionHas('error');
        $this->assertNull($activity->registrations()->where('user_id', $late->id)->value('checked_in_at'));
    }

    public function test_in_app_scanner_page_and_menu_entry(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/scanner')->assertOk()->assertSee('Scanner un QR code')->assertSee('data-scanner', false);
        $this->actingAs($member)->get('/defis')->assertOk()->assertSee(route('activities.scan'), false);
        auth()->logout();
        $this->get('/scanner')->assertRedirect();
    }

    public function test_qr_page_is_reserved_to_the_organizing_clubs(): void
    {
        $club = $this->club('ESPRIT', true);
        $other = $this->club('ISET', true);
        $president = User::factory()->create();
        $rival = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'president')->firstOrFail(), $president);
        $this->placeOn(OrgNode::query()->where('subtitle', $other->title)->where('role_key', 'president')->firstOrFail(), $rival);
        $activity = $this->activity($this->challenge(), $club);

        $this->actingAs($president)->get("/admin/activites/{$activity->id}/qr")->assertOk()->assertSee($activity->checkinUrl());
        $this->actingAs($rival)->get("/admin/activites/{$activity->id}/qr")->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/admin/activites/{$activity->id}/qr")->assertForbidden();
    }

    public function test_co_organizers_must_accept_and_share_the_score(): void
    {
        $lead = $this->club('ESPRIT', true);
        $partner = $this->club('ISET', true);
        $leadPresident = User::factory()->create();
        $partnerPresident = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $lead->title)->where('role_key', 'president')->firstOrFail(), $leadPresident);
        $this->placeOn(OrgNode::query()->where('subtitle', $partner->title)->where('role_key', 'president')->firstOrFail(), $partnerPresident);
        $juror = $this->committeeMember();
        $challenge = $this->challenge($juror);
        $activity = $this->activity($challenge, $lead);

        $this->actingAs($leadPresident)->post("/admin/activites/{$activity->id}/co-organisateurs", ['club_id' => $partner->id])->assertSessionHas('status');
        $organizer = $activity->organizers()->firstOrFail();
        $this->assertSame('pending', $organizer->status);
        $this->assertSame(1, \App\Models\AppNotification::query()->where('user_id', $partnerPresident->id)->where('type', 'activity.coorganizer')->count());
        $this->assertSame([$lead->id], $activity->fresh()->organizerClubIds());

        // Seul le club invité répond.
        $this->actingAs($leadPresident)->patch("/admin/activites/co-organisateurs/{$organizer->id}", ['status' => 'accepted'])->assertForbidden();
        $this->actingAs($partnerPresident)->get('/admin/activites')->assertOk()->assertSee('Invitations à co-organiser');
        $this->actingAs($partnerPresident)->patch("/admin/activites/co-organisateurs/{$organizer->id}", ['status' => 'accepted'])->assertSessionHas('status');
        $this->assertEqualsCanonicalizing([$lead->id, $partner->id], $activity->fresh()->organizerClubIds());

        Carbon::setTestNow($challenge->ends_at->copy()->addDay());
        foreach ($challenge->criteriaOf('theme') as $criterion) {
            ActivityScore::query()->create(['activity_id' => $activity->id, 'criterion_id' => $criterion->id, 'evaluator_id' => $juror->id, 'points' => $criterion->max_points]);
        }

        $ranking = ChallengeScoring::ranking($challenge);
        $this->assertCount(2, $ranking);
        $this->assertSame([1, 1], $ranking->pluck('rank')->all());
        $this->assertSame([15.0, 15.0], $ranking->pluck('points')->all());

        // Une invitation refusée ou retirée ne compte plus.
        $this->actingAs($leadPresident)->delete("/admin/activites/co-organisateurs/{$organizer->id}")->assertSessionHas('status');
        $this->assertCount(1, ChallengeScoring::ranking($challenge));
    }

    public function test_only_the_committee_scores_within_the_window_and_the_average_is_ranked(): void
    {
        $club = $this->club('ESPRIT');
        $rival = $this->club('ISET');
        [$juryA, $juryB] = [User::factory()->create(), User::factory()->create()];
        $challenge = $this->challenge($juryA);
        $challenge->committee()->attach($juryB->id);
        $theme = $this->activity($challenge, $club, ['title' => 'Atelier recyclage']);
        $offTheme = $this->activity($challenge, $club, ['title' => 'Soirée', 'in_theme' => false]);
        $other = $this->activity($challenge, $rival, ['title' => 'Course']);
        [$impact, $quality] = $challenge->criteriaOf('theme')->all();
        $initiative = $challenge->criteriaOf('off_theme')->first();
        $form = fn (array $points) => ['points' => $points];

        // Sans compte rendu validé, une activité n'est pas notée (même une fois la notation ouverte).
        Carbon::setTestNow($challenge->ends_at->copy()->addDay());
        $this->actingAs($juryA)->post("/defis/{$challenge->id}/evaluation/{$theme->id}", $form([$impact->id => 8, $quality->id => 4]))->assertSessionHas('error');
        $this->assertSame(0, ActivityScore::query()->count());
        Carbon::setTestNow();

        foreach ([$theme, $offTheme, $other] as $activity) {
            $this->validatedReport($activity);
        }

        // Avant la fin du défi : fermé.
        $this->actingAs($juryA)->get('/defis/'.$challenge->id.'/evaluation')->assertOk()->assertSee('Atelier recyclage');
        $this->actingAs($juryA)->post("/defis/{$challenge->id}/evaluation/{$theme->id}", $form([$impact->id => 8, $quality->id => 4]))->assertSessionHas('error');
        $this->assertSame(0, ActivityScore::query()->count());

        Carbon::setTestNow($challenge->ends_at->copy()->addDay());

        // Un non-membre du comité n'a pas accès ; les notes hors barème sont refusées.
        $this->actingAs(User::factory()->create())->get('/defis/'.$challenge->id.'/evaluation')->assertForbidden();
        $this->actingAs(User::factory()->create())->post("/defis/{$challenge->id}/evaluation/{$theme->id}", $form([$impact->id => 8, $quality->id => 4]))->assertForbidden();
        $this->actingAs($juryA)->post("/defis/{$challenge->id}/evaluation/{$theme->id}", $form([$impact->id => 11, $quality->id => 4]))->assertSessionHasErrors("points.{$impact->id}");
        $this->actingAs($juryA)->post("/defis/{$challenge->id}/evaluation/{$theme->id}", $form([$impact->id => 8]))->assertSessionHasErrors("points.{$quality->id}");
        $this->assertSame(0, ActivityScore::query()->count());

        $this->actingAs($juryA)->post("/defis/{$challenge->id}/evaluation/{$theme->id}", $form([$impact->id => 8, $quality->id => 4]))->assertSessionHas('status');
        $this->actingAs($juryB)->post("/defis/{$challenge->id}/evaluation/{$theme->id}", $form([$impact->id => 6, $quality->id => 2]))->assertSessionHas('status');
        // Le barème hors thématique est distinct (5 points maximum).
        $this->actingAs($juryA)->post("/defis/{$challenge->id}/evaluation/{$offTheme->id}", $form([$initiative->id => 4]))->assertSessionHas('status');
        $this->actingAs($juryA)->post("/defis/{$challenge->id}/evaluation/{$other->id}", $form([$impact->id => 3, $quality->id => 1]))->assertSessionHas('status');
        // Reprise : la note est mise à jour, pas dupliquée.
        $this->actingAs($juryA)->post("/defis/{$challenge->id}/evaluation/{$other->id}", $form([$impact->id => 4, $quality->id => 1]));
        $this->assertSame(2 + 2 + 1 + 2, ActivityScore::query()->count());

        $totals = ChallengeScoring::activityTotals($challenge);
        $this->assertSame(10.0, $totals[$theme->id]['total']);
        $this->assertSame(2, $totals[$theme->id]['evaluators']);
        $this->assertSame(4.0, $totals[$offTheme->id]['total']);
        $this->assertSame(5, $totals[$offTheme->id]['max']);

        $ranking = ChallengeScoring::ranking($challenge);
        $this->assertSame(['ESPRIT', 'ISET'], $ranking->map(fn ($row) => $row['club']->title)->all());
        $this->assertSame(14.0, $ranking[0]['points']);
        $this->assertSame(10.0, $ranking[0]['theme_points']);
        $this->assertSame(4.0, $ranking[0]['off_theme_points']);
        $this->assertSame(5.0, $ranking[1]['points']);

        // Les résultats restent cachés aux membres jusqu'à la date de publication.
        $member = User::factory()->create();
        $this->approvedMember($member, $club);
        $this->actingAs($member)->get('/defis/'.$challenge->id)->assertOk()->assertDontSee('Rang</th>', false)->assertDontSee('Atelier recyclage 14');
        Carbon::setTestNow($challenge->results_at->copy()->addMinute());
        $this->actingAs($member)->get('/defis/'.$challenge->id)->assertOk()->assertSee('ESPRIT')->assertSee('Rang</th>', false);
        // La notation est close à la publication.
        $this->actingAs($juryB)->post("/defis/{$challenge->id}/evaluation/{$other->id}", $form([$impact->id => 10, $quality->id => 5]))->assertSessionHas('error');
        $this->assertSame(5.0, ChallengeScoring::ranking($challenge)[1]['points']);
    }

    public function test_committee_members_cannot_score_their_own_club(): void
    {
        $club = $this->club('ESPRIT');
        $juror = $this->committeeMember();
        $this->approvedMember($juror, $club);
        $challenge = $this->challenge($juror);
        $activity = $this->activity($challenge, $club);
        [$impact, $quality] = $challenge->criteriaOf('theme')->all();
        Carbon::setTestNow($challenge->ends_at->copy()->addDay());

        $this->actingAs($juror)->post("/defis/{$challenge->id}/evaluation/{$activity->id}", ['points' => [$impact->id => 10, $quality->id => 5]])->assertSessionHas('error');
        $this->assertSame(0, ActivityScore::query()->count());
        $this->actingAs($juror)->get('/defis/'.$challenge->id.'/evaluation')->assertOk()->assertSee('Conflit d’intérêt');
    }

    public function test_scoring_is_incomplete_until_every_criterion_is_scored(): void
    {
        $club = $this->club('ESPRIT');
        $juror = $this->committeeMember();
        $challenge = $this->challenge($juror);
        $activity = $this->activity($challenge, $club);
        $impact = $challenge->criteriaOf('theme')->first();
        ActivityScore::query()->create(['activity_id' => $activity->id, 'criterion_id' => $impact->id, 'evaluator_id' => $juror->id, 'points' => 9]);

        $this->assertNull(ChallengeScoring::activityTotals($challenge)[$activity->id]['total']);
        $this->assertTrue(ChallengeScoring::ranking($challenge)->isEmpty());
    }

    public function test_challenge_pages_follow_the_calendar_and_the_module_switch(): void
    {
        $member = User::factory()->create();
        $this->approvedMember($member, $this->club('ESPRIT'));
        $running = $this->challenge();
        $draft = $this->challenge(null, ['title' => 'Défi secret', 'status' => 'draft']);

        $this->actingAs($member)->get('/defis')->assertOk()->assertSee('Défi Environnement')->assertDontSee('Défi secret');
        $this->actingAs($member)->get('/defis/'.$draft->id)->assertNotFound();
        $this->actingAs($member)->get('/defis/'.$running->id)->assertOk()->assertSee('Sensibilisation écologique')->assertSee('Impact')->assertSee('10 pts');

        $this->assertSame('running', $running->phase());
        Carbon::setTestNow($running->ends_at->copy()->addHour());
        $this->assertSame('closed', $running->fresh()->phase());
        Carbon::setTestNow($running->evaluation_at->copy()->addHour());
        $this->assertSame('evaluation', $running->fresh()->phase());
        Carbon::setTestNow($running->results_at->copy()->addHour());
        $this->assertSame('results', $running->fresh()->phase());
        Carbon::setTestNow();

        AppModule::query()->where('key', 'challenges.challenges')->update(['is_active' => false]);
        $this->actingAs($member)->get('/defis')->assertNotFound();
    }

    public function test_secondary_perimeters_of_a_club_and_the_membership_scope_also_count(): void
    {
        $club = $this->club('ESPRIT');
        $school = $this->challenge(null, ['title' => 'Défi Lycées', 'scope' => 'high-school']);
        $alumni = $this->challenge(null, ['title' => 'Défi Anciens', 'scope' => 'alumni']);
        $this->activity($school, $club, ['title' => 'Atelier lycées']);

        $member = User::factory()->create();
        $this->approvedMember($member, $club);
        $this->assertNotContains('high-school', \App\Support\Challenges\ActivityAccess::scopesOf($member));
        $this->actingAs($member)->get('/defis')->assertDontSee('Défi Lycées');

        // Le club rayonne aussi sur un périmètre secondaire.
        $club->update(['extra_scopes' => ['high-school']]);
        $this->assertContains('high-school', \App\Support\Challenges\ActivityAccess::scopesOf($member));
        $this->actingAs($member)->get('/defis')->assertSee('Défi Lycées')->assertDontSee('Défi Anciens');
        $this->actingAs($member)->get('/defis/'.$school->id)->assertOk();
        $this->assertContains('Atelier lycées', collect($this->actingAs($member)->get('/defis')->viewData('upcoming'))->pluck('title')->all());

        // Adhésion validée portant un autre périmètre.
        \App\Models\Membership::query()->where('user_id', $member->id)->update(['scope' => 'alumni']);
        $this->actingAs($member)->get('/defis')->assertSee('Défi Anciens');

        // Une adhésion en attente ne donne aucun périmètre.
        $pending = User::factory()->create();
        \App\Models\Membership::query()->create(['user_id' => $pending->id, 'scope' => 'alumni', 'node_id' => $club->id, 'status' => 'pending', 'membership_type' => 'member']);
        $this->assertSame([], \App\Support\Challenges\ActivityAccess::scopesOf($pending));
    }

    public function test_members_only_see_challenges_of_their_perimeters(): void
    {
        $club = $this->club('ESPRIT');
        $university = $this->challenge(null, ['title' => 'Défi Campus', 'scope' => 'university']);
        $school = $this->challenge(null, ['title' => 'Défi Lycées', 'scope' => 'high-school']);
        $invited = $this->challenge(null, ['title' => 'Défi Ouvert', 'scope' => 'high-school', 'invited_scopes' => ['university']]);
        $juror = $this->committeeMember();
        $jury = $this->challenge($juror, ['title' => 'Défi Jury', 'scope' => 'high-school']);

        $member = User::factory()->create();
        $this->approvedMember($member, $club);

        $this->actingAs($member)->get('/defis')->assertOk()->assertSee('Défi Campus')->assertSee('Défi Ouvert')->assertDontSee('Défi Lycées')->assertDontSee('Défi Jury');
        $this->actingAs($member)->get('/defis/'.$university->id)->assertOk();
        $this->actingAs($member)->get('/defis/'.$invited->id)->assertOk();
        $this->actingAs($member)->get('/defis/'.$school->id)->assertNotFound();

        // Sans rattachement : aucun défi. Un membre du comité voit les défis qu'il évalue.
        $this->actingAs(User::factory()->create())->get('/defis')->assertOk()->assertDontSee('Défi Campus');
        $this->actingAs($juror)->get('/defis')->assertOk()->assertSee('Défi Jury')->assertDontSee('Défi Campus');
        $this->actingAs($juror)->get('/defis/'.$jury->id)->assertOk();

        // Droits globaux : tous les défis.
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $this->actingAs($admin)->get('/defis')->assertOk()->assertSee('Défi Campus')->assertSee('Défi Lycées');
    }

    public function test_activity_page_shows_the_hero_with_cover_club_logo_and_registration(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $club = $this->club('ESPRIT');
        $club->update(['logo_path' => 'org/logos/esprit.png']);
        $activity = $this->activity($this->challenge(), $club, ['title' => 'Tounes el khadhra', 'cover_path' => 'activities/covers/hero.jpg', 'capacity' => 3]);
        $member = User::factory()->create();

        $page = $this->actingAs($member)->get("/activites/{$activity->id}")->assertOk();
        $page->assertSee('activities/covers/hero.jpg', false)->assertSee('org/logos/esprit.png', false)->assertSee('Logo ESPRIT', false);
        $page->assertSee('Tounes el khadhra')->assertSee('M’inscrire')->assertSee('id="inscription"', false)->assertSee('Plus que 3 place(s)');

        // Sans couverture ni logo : fond T.Link et initiales du club, l'inscription reste possible.
        $bare = $this->activity($this->challenge(null, ['title' => 'Autre défi']), $this->club('ISET'), ['title' => 'Sans image']);
        $this->actingAs($member)->get("/activites/{$bare->id}")->assertOk()->assertSee('M’inscrire')->assertDontSee('<img src="" ', false);

        // Inscription directe depuis le bandeau.
        $this->actingAs($member)->post("/activites/{$activity->id}/inscription")->assertSessionHas('status');
        $this->actingAs($member)->get("/activites/{$activity->id}")->assertSee('Vous êtes inscrit');

        // Activité terminée : le bandeau l'indique.
        $over = $this->activity($this->challenge(null, ['title' => 'Défi passé']), $club, ['title' => 'Déjà finie', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(2)]);
        $this->actingAs(User::factory()->create())->get("/activites/{$over->id}")->assertOk()->assertSee('Activité terminée');
    }

    public function test_club_manager_uploads_replaces_and_removes_the_activity_cover(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $club = $this->club('ESPRIT');
        $challenge = $this->challenge();
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $payload = fn (array $extra = []): array => $extra + [
            'challenge_id' => $challenge->id, 'club_id' => $club->id, 'title' => 'Avec couverture', 'type' => 'event', 'audience' => 'public', 'in_theme' => 1,
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'status' => 'published',
        ];

        $this->actingAs($admin)->get('/admin/activites/creer')->assertOk()->assertSee('Image de couverture')->assertSee('multipart/form-data');

        $this->actingAs($admin)->post('/admin/activites', $payload(['cover' => \Illuminate\Http\UploadedFile::fake()->create('virus.php', 10, 'text/x-php')]))->assertSessionHasErrors('cover');
        $this->actingAs($admin)->post('/admin/activites', $payload(['cover' => \Illuminate\Http\UploadedFile::fake()->image('big.jpg')->size(5000)]))->assertSessionHasErrors('cover');

        $this->actingAs($admin)->post('/admin/activites', $payload(['cover' => \Illuminate\Http\UploadedFile::fake()->image('hero.jpg', 1600, 600)]))->assertSessionHasNoErrors();
        $activity = ClubActivity::query()->where('title', 'Avec couverture')->firstOrFail();
        $first = $activity->cover_path;
        $this->assertStringStartsWith('activities/covers/', $first);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($first);

        // Remplacement : l'ancienne image est supprimée.
        $this->actingAs($admin)->put("/admin/activites/{$activity->id}", $payload(['cover' => \Illuminate\Http\UploadedFile::fake()->image('new.jpg')]))->assertSessionHasNoErrors();
        $second = $activity->fresh()->cover_path;
        $this->assertNotSame($first, $second);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($first);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($second);

        // Sans nouveau fichier, la couverture est conservée ; la case la retire.
        $this->actingAs($admin)->put("/admin/activites/{$activity->id}", $payload())->assertSessionHasNoErrors();
        $this->assertSame($second, $activity->fresh()->cover_path);
        $this->actingAs($admin)->put("/admin/activites/{$activity->id}", $payload(['cover_remove' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($activity->fresh()->cover_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($second);
    }

    public function test_navigation_shows_the_challenges_entry(): void
    {
        $this->actingAs(User::factory()->create())->get('/defis')->assertOk()->assertSee(route('challenges.index'), false);
    }

    public function test_activity_page_lists_upcoming_public_activities_and_registers_directly(): void
    {
        $club = $this->club('ESPRIT');
        $challenge = $this->challenge();
        $member = User::factory()->create();

        $open = $this->activity($challenge, $club, ['title' => 'Plage propre', 'capacity' => 2]);
        $this->activity($challenge, $club, ['title' => 'Avec formulaire', 'registration_fields' => [['label' => 'Taille de t-shirt', 'type' => 'text', 'required' => true]]]);
        $this->activity($challenge, $club, ['title' => 'Brouillon caché', 'status' => 'draft']);
        $this->activity($challenge, $club, ['title' => 'Déjà passée', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(2)]);
        $this->activity($challenge, $club, ['title' => 'Interne au club', 'audience' => 'club']);
        $this->activity($challenge, $club, ['title' => 'Réservée Tunimateurs', 'audience' => 'tunimateurs']);
        $this->activity($this->challenge(null, ['title' => 'Défi Brouillon', 'status' => 'draft']), $club, ['title' => 'Défi non publié']);
        // Le périmètre du défi n'est pas un filtre : seul le public « Public » compte, même sans aucun rattachement du membre.
        $this->activity($this->challenge(null, ['title' => 'Défi Lycées', 'scope' => 'high-school']), $club, ['title' => 'Autre périmètre']);

        $calendar = collect($this->actingAs($member)->get('/defis')->assertOk()->viewData('upcoming'))->keyBy('title');

        $this->assertSame(['Autre périmètre', 'Avec formulaire', 'Plage propre'], $calendar->keys()->sort()->values()->all());
        $this->assertSame('open', $calendar['Plage propre']['state']);
        $this->assertFalse($calendar['Plage propre']['needs_form']);
        $this->assertTrue($calendar['Avec formulaire']['needs_form']);
        $this->assertSame($open->starts_at->format('Y-m-d'), $calendar['Plage propre']['day']);

        // Inscription depuis la page Activité : retour sur la page, état mis à jour.
        $this->actingAs($member)->from('/defis')->post($calendar['Plage propre']['register_url'])->assertRedirect('/defis')->assertSessionHas('status');
        $this->assertSame('registered', collect($this->actingAs($member)->get('/defis')->viewData('upcoming'))->firstWhere('title', 'Plage propre')['state']);

        // Une fois complète, l'activité propose la liste d'attente ; les inscriptions closes sont signalées.
        $other = User::factory()->create();
        $open->update(['capacity' => 1]);
        $this->assertSame('full', collect($this->actingAs($other)->get('/defis')->viewData('upcoming'))->firstWhere('title', 'Plage propre')['state']);
        $open->update(['registration_ends_at' => now()->subHour()]);
        $this->assertSame('closed', collect($this->actingAs($other)->get('/defis')->viewData('upcoming'))->firstWhere('title', 'Plage propre')['state']);
    }

    public function test_upcoming_activities_are_sorted_by_date(): void
    {
        $club = $this->club('ESPRIT');
        $challenge = $this->challenge();
        $this->activity($challenge, $club, ['title' => 'Plus tard', 'starts_at' => now()->addDays(9), 'ends_at' => now()->addDays(9)->addHours(2)]);
        $this->activity($challenge, $club, ['title' => 'Bientôt', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(2)]);

        $titles = collect($this->actingAs(User::factory()->create())->get('/defis')->viewData('upcoming'))->pluck('title')->all();

        $this->assertSame(['Bientôt', 'Plus tard'], $titles);
    }

    public function test_upcoming_activities_leave_the_feed_and_live_on_the_activity_page(): void
    {
        $club = $this->club('ESPRIT');
        $this->activity($this->challenge(), $club, ['title' => 'Plage propre']);
        $member = User::factory()->create();

        $feed = $this->actingAs($member)->get('/feed')->assertOk();
        $feed->assertDontSee('calendarEnabled', false)->assertDontSee('Plage propre');

        $this->actingAs($member)->get('/defis')->assertOk()->assertSee('Activités à venir')->assertSee('Plage propre');
        $this->actingAs(User::factory()->create())->get('/defis')->assertOk()->assertSee('Activités à venir');
    }
}
