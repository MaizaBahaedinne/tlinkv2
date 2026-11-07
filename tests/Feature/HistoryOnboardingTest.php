<?php

namespace Tests\Feature;

use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\InfoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function validated(): User
    {
        return User::factory()->create(['onboarding_completed_at' => now()]);
    }

    private function mandate(int $year, string $status = 'closed'): Mandate
    {
        return Mandate::query()->create(['label' => $year.'-'.($year + 1), 'start_year' => $year, 'end_year' => $year + 1, 'bureau_name' => 'Bureau '.$year, 'status' => $status]);
    }

    private function club(): OrgNode
    {
        return OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'university_club', 'title' => 'Club Robotique', 'scope' => 'university', 'status' => 'active', 'sort_order' => 0]);
    }

    public function test_passing_the_quiz_then_the_welcome_questions_are_reachable(): void
    {
        $user = User::factory()->create();
        $attempt = InfoSession::start($user);
        $answers = [];
        foreach ($attempt->snapshot['questions'] as $question) {
            $answers[$question['id']] = $attempt->snapshot['correct'][$question['id']];
        }

        $this->actingAs($user)->post('/info-session/quiz/'.$attempt->id, ['answers' => $answers])->assertRedirect(route('info-session.show'));

        $this->actingAs($user->fresh())->get('/bienvenue')->assertOk()->assertSee('Ancien membre')->assertSee('Nouveau membre');

        $user->forceFill(['history_onboarding_at' => now()])->save();
        $second = InfoSession::start($user->fresh());
        $secondAnswers = [];
        foreach ($second->snapshot['questions'] as $question) {
            $secondAnswers[$question['id']] = $second->snapshot['correct'][$question['id']];
        }
        $this->actingAs($user->fresh())->post('/info-session/quiz/'.$second->id, ['answers' => $secondAnswers])->assertRedirect(route('info-session.show'));
    }

    public function test_the_welcome_page_needs_a_validated_account(): void
    {
        $this->get('/bienvenue')->assertRedirect('/connexion');
        $this->actingAs(User::factory()->create())->get('/bienvenue')->assertRedirect(route('info-session.show'));
        $this->actingAs(User::factory()->create())->post('/bienvenue/origine', ['origin' => 'new'])->assertForbidden();
    }

    public function test_a_new_member_goes_straight_to_his_first_membership_request(): void
    {
        $user = $this->validated();

        $this->actingAs($user)->post('/bienvenue/origine', ['origin' => 'new'])->assertRedirect(route('memberships.index'));

        $user->refresh();
        $this->assertSame('new', $user->member_origin);
        $this->assertNotNull($user->history_onboarding_at);
        $this->assertNull($user->joined_mandate_id);
        $this->actingAs($user)->post('/bienvenue/origine', ['origin' => 'inconnu'])->assertSessionHasErrors('origin');
    }

    public function test_an_alumnus_declares_his_entry_mandate_and_post_which_prefills_his_first_membership(): void
    {
        $old = $this->mandate(2021);
        $joined = $this->mandate(2023);
        $club = $this->club();
        $user = $this->validated();

        $this->actingAs($user)->post('/bienvenue/origine', ['origin' => 'alumni'])->assertRedirect(route('onboarding.history'));
        $this->actingAs($user)->get('/bienvenue')->assertOk()->assertSee('Quand as-tu rejoint le réseau')->assertSee('Mandat 2021-2022')->assertSee('Mandat 2023-2024');
        $this->assertNull($user->fresh()->history_onboarding_at);

        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_target' => 'club:'.$club->id])
            ->assertRedirect(route('onboarding.history'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mandate_participations', ['user_id' => $user->id, 'mandate_id' => $joined->id, 'node_id' => $club->id, 'status' => 'approved']);
        $user->refresh();
        $this->assertSame($joined->id, $user->joined_mandate_id);
        $this->assertNotNull($user->history_onboarding_at);

        $this->actingAs($user)->get('/bienvenue')->assertOk()->assertSee('Complète ton parcours depuis le mandat 2023-2024')
            ->assertSee('Avant mon arrivée dans le réseau')->assertSee('Mon arrivée');
        $this->assertNotNull($old->id);
    }

    public function test_the_entry_form_asks_scope_then_club_then_position(): void
    {
        $joined = $this->mandate(2023);
        $club = $this->club();
        $user = $this->validated();
        $this->actingAs($user)->post('/bienvenue/origine', ['origin' => 'alumni']);

        $page = $this->actingAs($user)->get('/bienvenue')->assertOk()->getContent();
        $this->assertLessThan(strpos($page, 'id="past-club"'), strpos($page, 'id="past-scope"'));
        $this->assertLessThan(strpos($page, 'id="past-position"'), strpos($page, 'id="past-club"'));

        // Une partie qui a des clubs : le club est obligatoire.
        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_scope' => 'university'])
            ->assertSessionHasErrors('past_club');
        $this->assertNull($user->fresh()->joined_mandate_id);

        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_scope' => 'university', 'past_club' => $club->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mandate_participations', ['user_id' => $user->id, 'mandate_id' => $joined->id, 'node_id' => $club->id, 'scope' => 'university']);
    }

    public function test_inactive_clubs_are_listed_and_selectable_for_past_mandates(): void
    {
        $joined = $this->mandate(2023);
        $inactive = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'university_club', 'title' => 'Club Dormant', 'scope' => 'university', 'status' => 'inactive', 'sort_order' => 0]);
        $position = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'access_level' => 'none', 'category' => 'direction', 'parent_id' => $inactive->id, 'title' => 'Membre actif', 'scope' => 'university', 'sort_order' => 0]);
        $user = $this->validated();
        $this->actingAs($user)->post('/bienvenue/origine', ['origin' => 'alumni']);

        $page = $this->actingAs($user)->get('/bienvenue')->assertOk()->getContent();
        $this->assertStringContainsString('Club Dormant (inactif)', $page);
        // Le poste reste grisé (disabled) tant que le club n'est pas choisi.
        $this->assertMatchesRegularExpression('/id="past-position"[^>]*\\sdisabled/', $page);

        $this->actingAs($user)->getJson('/adhesions/postes?target=club:'.$inactive->id)->assertOk();
        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_scope' => 'university', 'past_club' => $inactive->id, 'position_id' => $position->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mandate_participations', ['user_id' => $user->id, 'node_id' => $inactive->id]);
    }

    public function test_a_scope_without_clubs_needs_no_club(): void
    {
        $joined = $this->mandate(2023);
        $user = $this->validated();
        $this->actingAs($user)->post('/bienvenue/origine', ['origin' => 'alumni']);

        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_scope' => 'alumni'])->assertSessionHasNoErrors();
        $this->assertSame($joined->id, $user->fresh()->joined_mandate_id);
    }

    public function test_a_leadership_entry_post_waits_for_admin_approval(): void
    {
        $joined = $this->mandate(2023);
        $club = $this->club();
        $president = OrgNode::query()->create(['parent_id' => $club->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'single', 'access_level' => 'club_president', 'category' => 'university_club', 'title' => 'Président de club', 'sort_order' => 0]);
        $user = $this->validated();

        $this->actingAs($user)->post('/bienvenue/origine', ['origin' => 'alumni']);
        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_target' => 'club:'.$club->id, 'position_id' => $president->id])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mandate_participations', ['user_id' => $user->id, 'position_title' => 'Président de club', 'status' => 'pending']);
        $this->assertDatabaseHas('approval_requests', ['request_type' => 'mandate_leadership', 'requester_id' => $user->id]);
    }

    public function test_entry_validation_and_current_mandates_are_refused(): void
    {
        $current = $this->mandate(2026, 'current');
        $joined = $this->mandate(2023);
        $user = $this->validated();

        // Étape 2 impossible avant d'avoir répondu « ancien ».
        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_target' => 'scope:alumni'])->assertRedirect(route('onboarding.history'));
        $this->assertSame(0, MandateParticipation::query()->count());

        $this->actingAs($user)->post('/bienvenue/origine', ['origin' => 'alumni']);
        $this->actingAs($user)->post('/bienvenue/arrivee', [])->assertSessionHasErrors(['mandate_id', 'past_target']);
        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $current->id, 'past_target' => 'scope:alumni'])->assertSessionHasErrors('past_target');
        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_target' => 'scope:inconnu'])->assertSessionHasErrors('past_target');
        $this->assertNull($user->fresh()->joined_mandate_id);

        $this->actingAs($user)->get('/bienvenue')->assertDontSee('Mandat 2026-2027');
    }

    public function test_mandates_before_the_arrival_are_greyed_and_cannot_be_declared(): void
    {
        $old = $this->mandate(2021);
        $joined = $this->mandate(2023);
        $next = $this->mandate(2024);
        $user = $this->validated();
        $user->forceFill(['member_origin' => 'alumni', 'joined_mandate_id' => $joined->id, 'history_onboarding_at' => now()])->save();

        $page = $this->actingAs($user)->get('/adhesions')->assertOk()->assertSee('Avant mon arrivée')->assertSee('Vous avez rejoint le réseau au mandat');
        $page->assertDontSee('data-mandate-id="'.$old->id.'"', false);
        $page->assertSee('data-mandate-id="'.$joined->id.'"', false)->assertSee('data-mandate-id="'.$next->id.'"', false);

        $this->actingAs($user)->post('/adhesions/mandats', ['mandate_id' => $old->id, 'past_target' => 'scope:alumni'])->assertSessionHasErrorsIn('past', 'past_target');
        $this->assertSame(0, MandateParticipation::query()->count());

        $this->actingAs($user)->post('/adhesions/mandats', ['mandate_id' => $next->id, 'past_target' => 'scope:alumni'])->assertSessionHasNoErrors();
        $this->assertSame(1, MandateParticipation::query()->count());
    }

    public function test_an_entry_already_declared_and_approved_lets_the_alumnus_through(): void
    {
        $joined = $this->mandate(2023);
        $club = $this->club();
        $user = $this->validated();
        $user->forceFill(['member_origin' => 'alumni'])->save();

        // Participation déjà déclarée depuis « Mes adhésions » (acceptée).
        $this->actingAs($user)->post('/adhesions/mandats', ['mandate_id' => $joined->id, 'past_target' => 'club:'.$club->id])->assertSessionHasNoErrors();
        $this->assertSame(1, MandateParticipation::query()->count());

        $this->actingAs($user)->post('/bienvenue/arrivee', ['mandate_id' => $joined->id, 'past_target' => 'club:'.$club->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('onboarding.history'));

        $user->refresh();
        $this->assertSame($joined->id, $user->joined_mandate_id);
        $this->assertNotNull($user->history_onboarding_at);
        $this->assertSame(1, MandateParticipation::query()->count());
        $this->actingAs($user)->get('/feed')->assertOk();
    }

    public function test_an_entry_already_pending_also_lets_the_alumnus_through_without_a_duplicate(): void
    {
        $joined = $this->mandate(2023);
        $club = $this->club();
        $president = OrgNode::query()->create(['parent_id' => $club->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'single', 'access_level' => 'club_president', 'category' => 'university_club', 'title' => 'Président de club', 'sort_order' => 0]);
        $user = $this->validated();
        $user->forceFill(['member_origin' => 'alumni'])->save();

        $payload = ['mandate_id' => $joined->id, 'past_target' => 'club:'.$club->id, 'position_id' => $president->id];
        $this->actingAs($user)->post('/adhesions/mandats', $payload)->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/bienvenue/arrivee', $payload)->assertSessionHasNoErrors();

        $this->assertSame($joined->id, $user->fresh()->joined_mandate_id);
        $this->assertSame(1, MandateParticipation::query()->count());
        $this->assertSame(1, \App\Models\ApprovalRequest::query()->where('request_type', 'mandate_leadership')->count());
    }

    public function test_reloading_the_post_urls_goes_back_to_the_welcome_page(): void
    {
        $this->actingAs($this->validated())->get('/bienvenue/origine')->assertRedirect('/bienvenue');
        $this->actingAs($this->validated())->get('/bienvenue/arrivee')->assertRedirect('/bienvenue');
    }

    public function test_members_who_have_not_answered_are_blocked_on_every_page_except_the_welcome_steps(): void
    {
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now()]);

        foreach (['/feed', '/adhesions', '/profil'] as $path) {
            $this->actingAs($user)->get($path)->assertRedirect(route('integration.show'));
        }

        $this->actingAs($user)->get('/integration')->assertOk()->assertSee('Ancien ou nouveau membre ?');
        $this->actingAs($user)->get('/bienvenue')->assertOk()->assertSee('value="alumni"', false)->assertSee('value="new"', false);
    }

    public function test_the_hub_asks_an_alumnus_to_finish_his_arrival_and_the_block_lifts_once_the_whole_flow_is_done(): void
    {
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now(), 'member_origin' => 'alumni']);

        $this->actingAs($user)->get('/feed')->assertRedirect(route('integration.show'));

        $user->forceFill(['history_onboarding_at' => now(), 'member_origin' => 'new'])->save();
        // Étape suivante : le profil reste à compléter, le fil reste fermé.
        $this->actingAs($user)->get('/feed')->assertRedirect(route('integration.show'));
        $this->actingAs($user)->get('/profil')->assertOk();
    }

    public function test_the_block_does_not_apply_during_impersonation(): void
    {
        $user = User::factory()->inOnboarding()->create();
        $this->actingAs($user)->withSession(['impersonator_id' => 999])->get('/feed')->assertOk();
    }
}
