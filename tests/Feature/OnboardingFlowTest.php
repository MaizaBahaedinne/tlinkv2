<?php

namespace Tests\Feature;

use App\Models\Mandate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Support\EmailVerificationCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'gender' => 'female',
            'birth_date' => '2000-05-17',
            'address' => '12 rue de la Liberté, Tunis',
            'facebook_url' => 'https://www.facebook.com/test.membre',
            'privacy_settings' => [
                'educations' => [['school' => 'ISI']],
                'skill_keywords' => ['PHP'],
                'languages' => [['name' => 'Français', 'level' => 'natif']],
            ],
        ]);
    }

    /** Compte arrivé à la dernière étape : e-mail, info session, ancien/nouveau et profil faits (ancien membre par défaut). */
    private function readyForPlacement(string $origin = 'alumni'): User
    {
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now(), 'history_onboarding_at' => now(), 'member_origin' => $origin]);
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

    /** Envoie un code et le renvoie en clair (tel que reçu par e-mail). */
    private function sentCode(User $user): string
    {
        $code = '';
        Notification::assertSentTo($user, EmailVerificationCode::class, function (EmailVerificationCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        return $code;
    }

    public function test_the_verification_code_is_mailed_and_unlocks_the_next_step(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->inOnboarding()->create();

        $this->actingAs($user)->post('/email/verification')->assertSessionHas('status');
        $code = $this->sentCode($user);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->actingAs($user)->get('/integration')->assertOk()->assertSee('code à 6 chiffres')->assertSee('15 minutes');
        $this->actingAs($user)->post('/email/verifier', ['code' => $code])->assertRedirect(route('integration.show'))->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user->fresh())->get('/info-session')->assertOk();
    }

    public function test_the_mail_carries_the_code_and_its_validity(): void
    {
        $mail = (new EmailVerificationCode('042137'))->toMail(User::factory()->make(['first_name' => 'Amel']));

        $this->assertStringContainsString('042137', $mail->subject);
        $this->assertContains('**042137**', $mail->introLines);
        $this->assertStringContainsString('15 minutes', implode(' ', array_map('strval', $mail->introLines)));
    }

    public function test_a_wrong_code_is_refused_and_five_failures_destroy_it(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->inOnboarding()->create();
        $this->actingAs($user)->post('/email/verification');
        $code = $this->sentCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->actingAs($user)->post('/email/verifier', ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        foreach (range(1, 4) as $attempt) {
            $this->actingAs($user)->post('/email/verifier', ['code' => $wrong]);
        }

        // Le code juste est lui aussi inutilisable après 5 essais ratés.
        $this->actingAs($user)->post('/email/verifier', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_code_expires_after_fifteen_minutes(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->inOnboarding()->create();
        $this->actingAs($user)->post('/email/verification');
        $code = $this->sentCode($user);

        $this->travel(14)->minutes();
        $this->assertSame(EmailVerificationCodes::INVALID, EmailVerificationCodes::check($user, '999999' === $code ? '888888' : '999999'));

        $this->travel(2)->minutes();
        $this->actingAs($user)->post('/email/verifier', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_new_code_replaces_the_previous_one_and_malformed_codes_are_refused(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->inOnboarding()->create();
        $first = EmailVerificationCodes::issue($user);
        $second = EmailVerificationCodes::issue($user);

        if ($first !== $second) {
            $this->assertSame(EmailVerificationCodes::INVALID, EmailVerificationCodes::check($user, $first));
        }

        $this->actingAs($user)->post('/email/verifier', ['code' => 'abc'])->assertSessionHasErrors('code');
        $this->actingAs($user)->post('/email/verifier', ['code' => ''])->assertSessionHasErrors('code');
        $this->actingAs($user)->post('/email/verifier', ['code' => substr($second, 0, 3).' '.substr($second, 3)])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_code_sent_to_the_old_address_does_not_validate_the_corrected_one(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->inOnboarding()->create();
        $old = EmailVerificationCodes::issue($user);

        $this->actingAs($user)->post('/integration/email', ['email' => 'corrige@example.com'])->assertSessionHasNoErrors();

        $this->assertNotSame(EmailVerificationCodes::OK, EmailVerificationCodes::check($user->fresh(), $old));
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_old_signed_link_no_longer_exists(): void
    {
        $user = User::factory()->unverified()->inOnboarding()->create();

        $this->actingAs($user)->get('/email/verifier/'.$user->id.'/'.sha1($user->email))->assertNotFound();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_address_can_be_corrected_while_unverified_only(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->inOnboarding()->create();

        $this->actingAs($user)->post('/integration/email', ['email' => 'Nouveau@Example.com'])->assertSessionHasNoErrors();
        $this->assertSame('nouveau@example.com', $user->fresh()->email);
        Notification::assertSentTo($user->fresh(), EmailVerificationCode::class);

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
        Notification::assertSentTo($user, EmailVerificationCode::class);
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

    public function test_the_hub_marks_finished_steps_done_and_the_others_pending(): void
    {
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now()]);

        $html = $this->actingAs($user)->get('/integration')->assertOk()->getContent();

        $this->assertStringContainsString('data-step="email" data-state="done"', $html);
        $this->assertStringContainsString('data-step="info_session" data-state="done"', $html);
        $this->assertStringContainsString('data-step="history" data-state="current"', $html);
        $this->assertStringContainsString('data-step="profile" data-state="pending"', $html);
        $this->assertStringContainsString('En attente', $html);
        $this->assertStringContainsString('Terminée', $html);
    }

    public function test_skills_and_languages_are_required_but_experience_is_not(): void
    {
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now(), 'history_onboarding_at' => now()]);
        Profile::query()->create(['user_id' => $user->id, 'photo_path' => 'profiles/avatars/test.jpg', 'privacy_settings' => [
            'educations' => [['school' => 'ISI']],
        ]]);

        $missing = \App\Support\ProfileCompleteness::missing($user->fresh());
        $this->assertSame(['votre genre', 'votre date de naissance', 'votre adresse', 'votre page Facebook', 'vos compétences', 'vos langues'], $missing);

        $user->profile->update(['gender' => 'male', 'birth_date' => '1999-01-02', 'address' => 'Sfax', 'facebook_url' => 'https://facebook.com/x', 'privacy_settings' => [
            'educations' => [['school' => 'ISI']],
            'skill_keywords' => ['PHP'],
            'languages' => [['name' => 'Français', 'level' => 'natif']],
        ]]);

        $this->assertSame([], \App\Support\ProfileCompleteness::missing($user->fresh()));
    }

    public function test_the_profile_is_filled_directly_on_the_integration_page(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now(), 'history_onboarding_at' => now(), 'member_origin' => 'new']);

        $this->actingAs($user)->get('/integration')->assertOk()
            ->assertSee('name="facebook_url"', false)->assertSee('name="birth_date"', false)->assertSee('name="skills"', false)->assertSee('Enregistrer mon profil');

        $this->actingAs($user)->post('/integration/profil', [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('moi.jpg'),
            'gender' => 'female',
            'birth_date' => '2001-03-04',
            'address' => '5 avenue Habib Bourguiba, Tunis',
            'facebook_url' => 'https://www.facebook.com/moi',
            'educations' => [['degree_type' => 'licence', 'diploma_name' => 'Informatique', 'school' => 'ISI']],
            'skills' => 'Laravel, Gestion de projet',
            'languages' => [['name' => 'Français', 'level' => 'Natif'], ['name' => '', 'level' => 'A1']],
        ])->assertRedirect(route('integration.show'))->assertSessionHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertSame('female', $profile->gender);
        $this->assertSame('2001-03-04', $profile->birth_date->toDateString());
        $this->assertSame(['Laravel', 'Gestion de projet'], $profile->privacy_settings['skill_keywords']);
        $this->assertCount(1, $profile->privacy_settings['languages']);
        $this->assertNotNull($profile->photo_path);
        $this->assertSame([], \App\Support\ProfileCompleteness::missing($user->fresh()));
    }

    public function test_the_inline_profile_requires_every_mandatory_field(): void
    {
        $user = User::factory()->inOnboarding()->create(['onboarding_completed_at' => now(), 'history_onboarding_at' => now(), 'member_origin' => 'new']);

        $this->actingAs($user)->post('/integration/profil', ['facebook_url' => 'https://example.com/moi', 'skills' => ''])
            ->assertSessionHasErrors(['avatar', 'gender', 'birth_date', 'address', 'facebook_url', 'skills']);
    }

    public function test_a_new_member_joins_a_club_only_while_an_alumnus_may_target_entities_and_positions(): void
    {
        $this->currentMandate();
        $club = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'direction', 'title' => 'Club Robotique', 'scope' => 'university', 'status' => 'active', 'sort_order' => 0]);
        $position = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'access_level' => 'none', 'category' => 'direction', 'title' => 'Membre actif', 'scope' => 'university', 'sort_order' => 0]);

        $new = $this->readyForPlacement('new');
        $page = $this->actingAs($new)->get('/integration')->assertOk();
        $page->assertSee('Adhésion à un club')->assertSee('club:'.$club->id, false)->assertDontSee('scope:university', false)->assertDontSee('name="position_id"', false);

        $this->actingAs($new)->post('/adhesions', ['target' => 'scope:university', 'motivation_letter' => self::LETTER])->assertSessionHasErrors('target');
        $this->actingAs($new)->post('/adhesions', ['target' => 'club:'.$club->id, 'position_id' => $position->id, 'motivation_letter' => self::LETTER])->assertSessionHasErrors('position_id');
        $this->assertSame(0, Membership::query()->count());
        $this->actingAs($new)->post('/adhesions', ['target' => 'club:'.$club->id, 'motivation_letter' => self::LETTER])->assertSessionHasNoErrors();
        $this->assertSame(1, Membership::query()->count());

        $alumnus = $this->readyForPlacement('alumni');
        $this->actingAs($alumnus)->get('/integration')->assertOk()->assertSee('Adhésion ou poste')->assertSee('scope:university', false)->assertSee('name="position_id"', false);
        $this->actingAs($alumnus)->post('/adhesions', ['target' => 'scope:university', 'position_id' => $position->id, 'motivation_letter' => self::LETTER])->assertSessionHasNoErrors();
        $this->assertSame($position->id, Membership::query()->where('user_id', $alumnus->id)->value('position_id'));
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
