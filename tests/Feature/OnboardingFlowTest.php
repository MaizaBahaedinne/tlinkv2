<?php

namespace Tests\Feature;

use App\Models\Mandate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private const LETTER = 'Je souhaite rejoindre la Fondation pour contribuer activement à ses projets et à ses équipes.';

    private function completeProfile(User $user, bool $photo = true): void
    {
        Profile::query()->updateOrCreate(['user_id' => $user->id], [
            'photo_path' => $photo ? 'profiles/avatars/test.jpg' : null,
            'privacy_settings' => [
                'educations' => [['school' => 'ISI']],
                'professional_experiences' => [['company' => 'Acme', 'role' => 'Stagiaire']],
                'skill_keywords' => ['PHP'],
            ],
        ]);
    }

    /** Compte arrivé à la dernière étape : e-mail, info session, ancien/nouveau et profil faits. */
    private function readyForPlacement(): User
    {
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now(), 'history_onboarding_at' => now(), 'member_origin' => 'new']);
        $this->completeProfile($user);

        return $user->fresh();
    }

    private function currentMandate(): Mandate
    {
        return Mandate::query()->create(['label' => '2026-2027', 'start_year' => 2026, 'end_year' => 2027, 'bureau_name' => 'Bureau 2026', 'status' => 'current']);
    }

    public function test_unverified_email_blocks_everything_but_the_email_step(): void
    {
        $user = User::factory()->unverified()->inOnboarding()->create();

        $this->actingAs($user)->get('/feed')->assertRedirect(route('integration.show'));
        $this->actingAs($user)->get('/info-session')->assertRedirect(route('integration.show'));
        $this->actingAs($user)->get('/integration')->assertOk()->assertSee('Confirmez votre adresse e-mail');
        $this->actingAs($user)->postJson('/posts', ['body' => 'x'])->assertForbidden();
    }

    public function test_the_verification_mail_is_sent_and_the_signed_link_unlocks_the_next_step(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->inOnboarding()->create();

        $this->actingAs($user)->post('/email/verification')->assertSessionHas('status');
        Notification::assertSentTo($user, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url)->assertRedirect(route('integration.show'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get('/info-session')->assertOk();
    }

    public function test_a_forged_verification_link_is_refused(): void
    {
        $user = User::factory()->unverified()->inOnboarding()->create();

        $this->actingAs($user)->get('/email/verifier/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_address_can_be_corrected_while_unverified_only(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->inOnboarding()->create();

        $this->actingAs($user)->post('/integration/email', ['email' => 'Nouveau@Example.com'])->assertSessionHasNoErrors();
        $this->assertSame('nouveau@example.com', $user->fresh()->email);
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);

        $verified = User::factory()->inOnboarding()->create();
        $this->actingAs($verified)->post('/integration/email', ['email' => 'autre@example.com'])->assertForbidden();
    }

    public function test_registration_sends_the_verification_mail_and_lands_on_the_hub(): void
    {
        Notification::fake();

        $this->post('/inscription', [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'jean@example.com',
            'password' => 'Un-mot-de-passe-solide-42', 'password_confirmation' => 'Un-mot-de-passe-solide-42',
        ])->assertRedirect();

        $user = User::query()->where('email', 'jean@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertNull($user->onboarding_done_at);
        $this->get('/feed')->assertRedirect(route('integration.show'));
    }

    public function test_the_profile_step_requires_a_photo_and_a_cv(): void
    {
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now(), 'history_onboarding_at' => now()]);
        $this->completeProfile($user, photo: false);

        $this->actingAs($user)->get('/feed')->assertRedirect(route('integration.show'));
        $this->actingAs($user)->get('/integration')->assertOk()->assertSee('une photo de profil');
        $this->actingAs($user)->get('/profil')->assertOk();
    }

    public function test_the_placement_request_blocks_until_a_superior_approves_it(): void
    {
        $this->currentMandate();
        $reviewer = User::factory()->create();
        $this->grantAccess($reviewer, 'entity_admin', 'university');
        $user = $this->readyForPlacement();

        $this->actingAs($user)->get('/feed')->assertRedirect(route('integration.show'));
        $this->actingAs($user)->get('/integration')->assertOk()->assertSee('Adhésion ou poste pour le mandat 2026-2027');

        $this->actingAs($user)->post('/adhesions', ['target' => 'scope:university', 'motivation_letter' => self::LETTER])
            ->assertRedirect(route('integration.show'));

        $membership = Membership::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $membership->status);

        // En attente : toujours bloqué, avec le nom du validateur.
        $this->actingAs($user)->get('/feed')->assertRedirect(route('integration.show'));
        $this->actingAs($user)->get('/integration')->assertOk()->assertSee('En attente de validation')->assertSee($reviewer->name);

        $approval = \App\Models\ApprovalRequest::query()->where('approvable_id', $membership->id)->firstOrFail();
        $this->actingAs($reviewer)->postJson("/api/v1/approvals/{$approval->id}/decision", ['decision' => 'approved', 'explanation' => 'Bienvenue'])->assertSuccessful();

        $this->actingAs($user->fresh())->get('/feed')->assertOk();
        $this->assertNotNull($user->fresh()->onboarding_done_at);
    }

    public function test_a_rejected_request_keeps_the_member_blocked_and_lets_him_try_again(): void
    {
        $this->currentMandate();
        $reviewer = User::factory()->create();
        $this->grantAccess($reviewer, 'entity_admin', 'university');
        $user = $this->readyForPlacement();

        $this->actingAs($user)->post('/adhesions', ['target' => 'scope:university', 'motivation_letter' => self::LETTER]);
        $approval = \App\Models\ApprovalRequest::query()->firstOrFail();
        $this->actingAs($reviewer)->postJson("/api/v1/approvals/{$approval->id}/decision", ['decision' => 'rejected', 'explanation' => 'Dossier incomplet'])->assertSuccessful();

        $this->actingAs($user->fresh())->get('/feed')->assertRedirect(route('integration.show'));
        $this->actingAs($user->fresh())->get('/integration')->assertOk()->assertSee('Dossier incomplet')->assertSee('Envoyer ma demande');
    }

    public function test_a_position_candidacy_makes_the_member_the_holder_once_approved(): void
    {
        $this->currentMandate();
        $reviewer = User::factory()->create();
        $this->grantAccess($reviewer, 'entity_admin', 'university');
        $position = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'access_level' => 'none', 'category' => 'direction', 'title' => 'Membre actif', 'scope' => 'university', 'sort_order' => 0]);
        $user = $this->readyForPlacement();

        $this->actingAs($user)->post('/adhesions', ['target' => 'scope:university', 'position_id' => $position->id, 'motivation_letter' => self::LETTER])->assertSessionHasNoErrors();

        $membership = Membership::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame($position->id, $membership->position_id);

        $approval = \App\Models\ApprovalRequest::query()->where('approvable_id', $membership->id)->firstOrFail();
        $this->actingAs($reviewer)->postJson("/api/v1/approvals/{$approval->id}/decision", ['decision' => 'approved'])->assertSuccessful();

        $this->assertTrue(OrgAssignment::query()->where('node_id', $position->id)->whereHas('person', fn ($q) => $q->where('user_id', $user->id))->exists());
        $this->actingAs($user->fresh())->get('/feed')->assertOk();
    }

    public function test_a_position_granting_rights_can_only_be_approved_by_a_global_admin(): void
    {
        $this->currentMandate();
        $entityAdmin = User::factory()->create();
        $this->grantAccess($entityAdmin, 'entity_admin', 'university');
        $global = User::factory()->create();
        $this->grantAccess($global, 'global_admin');
        $position = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'single', 'access_level' => 'entity_admin', 'category' => 'direction', 'title' => 'Président', 'scope' => 'university', 'sort_order' => 0]);
        $user = $this->readyForPlacement();

        $this->actingAs($user)->post('/adhesions', ['target' => 'scope:university', 'position_id' => $position->id, 'motivation_letter' => self::LETTER])->assertSessionHasNoErrors();

        $approval = \App\Models\ApprovalRequest::query()->firstOrFail();
        $this->assertSame($global->id, $approval->assigned_reviewer_id);

        $approval->update(['assigned_reviewer_id' => null]);
        $this->actingAs($entityAdmin)->postJson("/api/v1/approvals/{$approval->id}/decision", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($global)->postJson("/api/v1/approvals/{$approval->id}/decision", ['decision' => 'approved'])->assertSuccessful();
        $this->assertSame('approved', Membership::query()->firstOrFail()->status);
    }

    public function test_a_position_outside_the_chosen_scope_is_refused(): void
    {
        $this->currentMandate();
        $other = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'access_level' => 'none', 'category' => 'direction', 'title' => 'Ailleurs', 'scope' => 'high-school', 'sort_order' => 0]);
        $user = $this->readyForPlacement();

        $this->actingAs($user)->post('/adhesions', ['target' => 'scope:university', 'position_id' => $other->id, 'motivation_letter' => self::LETTER])->assertSessionHasErrors('position_id');
        $this->assertSame(0, Membership::query()->count());
    }

    public function test_without_a_current_mandate_the_flow_ends_after_the_profile(): void
    {
        $user = $this->readyForPlacement();

        $this->actingAs($user)->get('/feed')->assertOk();
        $this->assertNotNull($user->fresh()->onboarding_done_at);
    }

    public function test_the_api_is_blocked_too_but_auth_endpoints_stay_open(): void
    {
        $user = User::factory()->inOnboarding()->create();

        $this->actingAs($user)->getJson('/api/v1/notifications')->assertForbidden()->assertJsonPath('onboarding_step', 'info_session');
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_existing_accounts_are_not_blocked(): void
    {
        $this->currentMandate();

        $this->actingAs(User::factory()->create())->get('/feed')->assertOk();
    }

    public function test_the_hub_redirects_to_the_feed_once_everything_is_done(): void
    {
        $this->actingAs(User::factory()->create())->get('/integration')->assertRedirect(route('home'));
    }

    public function test_the_restart_command_puts_an_existing_account_back_at_the_first_step(): void
    {
        $this->currentMandate();
        $user = User::factory()->create(['email_verified_at' => now(), 'onboarding_completed_at' => now(), 'history_onboarding_at' => now()]);

        $this->artisan('onboarding:restart', ['email' => $user->email])->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->onboarding_done_at);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('email', \App\Support\Onboarding::current($user));
        $this->actingAs($user)->get('/feed')->assertRedirect(route('integration.show'));
    }
}
