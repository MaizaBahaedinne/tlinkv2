<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\PositionApplication;
use App\Models\PositionInvitation;
use App\Models\User;
use App\Notifications\PositionInvitationNotification;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class PositionInvitationTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function position(string $capacity = 'single'): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => OrgNode::query()->where('system_key', 'university-office')->value('id'),
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => $capacity,
            'access_level' => 'none',
            'category' => 'national_office',
            'title' => 'Responsable communication',
            'sort_order' => 0,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    /** @return array<string, string> */
    private function invitee(string $email = 'amel@example.com', string $first = 'Amel', string $last = 'Ben Salah'): array
    {
        return ['first_name' => $first, 'last_name' => $last, 'email' => $email];
    }

    /** Clair du lien envoyé à l'adresse donnée (le jeton n'est stocké que haché). */
    private function sentToken(string $email): string
    {
        $token = null;

        Notification::assertSentOnDemand(PositionInvitationNotification::class, function (PositionInvitationNotification $notification, array $channels, $notifiable) use ($email, &$token): bool {
            if (array_key_exists('mail', $notifiable->routes) && array_key_exists($email, (array) $notifiable->routes['mail'])) {
                $token = $notification->token;

                return true;
            }

            return false;
        });

        return (string) $token;
    }

    /** @return array<string, mixed> */
    private function registration(array $override = []): array
    {
        $this->seed(GovernorateSeeder::class);

        return array_merge([
            'first_name' => 'Amel',
            'last_name' => 'Ben Salah',
            'password' => 'Motdepasse-Solide-2026',
            'password_confirmation' => 'Motdepasse-Solide-2026',
            'phone' => '+216 20 123 456',
            'gender' => 'female',
            'birth_date' => '2000-05-12',
            'governorate_id' => Governorate::query()->first()->id,
            'message' => 'Je veux contribuer à la communication de la Fondation.',
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ], $override);
    }

    public function test_admin_invites_a_person_by_email_and_only_the_hash_is_stored(): void
    {
        Notification::fake();
        $position = $this->position();

        $this->actingAs($this->admin())
            ->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])
            ->assertOk()
            ->assertJsonPath('invitation_result.sent.0.email', 'amel@example.com');

        $token = $this->sentToken('amel@example.com');
        $invitation = PositionInvitation::query()->firstOrFail();

        $this->assertSame(40, strlen($token));
        $this->assertSame(PositionInvitation::hashToken($token), $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame('Amel Ben Salah', $invitation->fullName());
        $this->assertSame('pending', $invitation->status());
        $this->assertSame(1, $invitation->send_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'orgchart.invitation.sent']);
    }

    public function test_mail_content_names_the_position_and_links_to_the_personal_page(): void
    {
        Notification::fake();
        $position = $this->position();

        $this->actingAs($this->admin())->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])->assertOk();

        $token = $this->sentToken('amel@example.com');
        $invitation = PositionInvitation::query()->firstOrFail();
        $mail = (new PositionInvitationNotification($invitation, $token))->toMail(new \stdClass());

        $this->assertStringContainsString('Responsable communication', $mail->subject);
        $this->assertSame(route('position.invitation', $token), $mail->actionUrl);
    }

    public function test_single_position_accepts_one_invitee_and_multiple_position_several(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $single = $this->position();
        $multiple = $this->position('multiple');
        $two = [$this->invitee('a@example.com', 'A', 'A'), $this->invitee('b@example.com', 'B', 'B')];

        $this->actingAs($admin)->postJson('/organigramme/blocs/'.$single->id.'/invitations', ['invitees' => $two])->assertUnprocessable();
        $this->assertSame(0, PositionInvitation::query()->count());

        $this->actingAs($admin)->postJson('/organigramme/blocs/'.$multiple->id.'/invitations', ['invitees' => $two])
            ->assertOk()
            ->assertJsonCount(2, 'invitation_result.sent');
        $this->assertSame(2, PositionInvitation::query()->where('node_id', $multiple->id)->count());
    }

    public function test_invitations_are_validated_in_french(): void
    {
        Notification::fake();
        $position = $this->position('multiple');

        $this->actingAs($this->admin())
            ->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [['first_name' => 'A', 'last_name' => '', 'email' => 'pas-un-email']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invitees.0.last_name', 'invitees.0.email']);

        $this->actingAs($this->admin())
            ->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee(), $this->invitee()]])
            ->assertJsonValidationErrors('invitees.1.email');
    }

    public function test_taken_single_position_cannot_receive_invitations(): void
    {
        Notification::fake();
        $position = $this->position();
        $holder = User::factory()->create();
        $person = OrgPerson::query()->create(['user_id' => $holder->id, 'full_name' => $holder->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $this->actingAs($this->admin())
            ->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])
            ->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_someone_already_holding_a_multiple_position_is_skipped(): void
    {
        Notification::fake();
        $position = $this->position('multiple');
        $holder = User::factory()->create(['email' => 'titulaire@example.com']);
        $person = OrgPerson::query()->create(['user_id' => $holder->id, 'full_name' => $holder->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $this->actingAs($this->admin())
            ->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee('titulaire@example.com'), $this->invitee('nouveau@example.com', 'N', 'N')]])
            ->assertOk()
            ->assertJsonPath('invitation_result.skipped.0.email', 'titulaire@example.com')
            ->assertJsonPath('invitation_result.sent.0.email', 'nouveau@example.com');

        $this->assertSame(1, PositionInvitation::query()->count());
    }

    public function test_only_editors_can_invite_resend_or_revoke(): void
    {
        Notification::fake();
        $position = $this->position();
        $invitation = PositionInvitation::query()->create(['node_id' => $position->id, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'token_hash' => PositionInvitation::hashToken('x'), 'expires_at' => now()->addDay()]);
        $member = User::factory()->create();

        $this->actingAs($member)->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])->assertForbidden();
        $this->actingAs($member)->postJson('/organigramme/invitations/'.$invitation->id.'/renvoyer')->assertForbidden();
        $this->actingAs($member)->deleteJson('/organigramme/invitations/'.$invitation->id)->assertForbidden();

        Notification::assertNothingSent();
        $this->assertNull($invitation->fresh()->revoked_at);
    }

    public function test_only_positions_can_receive_invitations(): void
    {
        Notification::fake();
        $unit = OrgNode::query()->create([
            'parent_id' => OrgNode::query()->where('system_key', 'university-office')->value('id'),
            'relation_type' => 'hierarchy',
            'kind' => 'unit',
            'capacity' => 'single',
            'access_level' => 'none',
            'category' => 'national_office',
            'title' => 'Pôle numérique',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin())
            ->postJson('/organigramme/blocs/'.$unit->id.'/invitations', ['invitees' => [$this->invitee()]])
            ->assertUnprocessable();
    }

    public function test_resending_rotates_the_link_and_kills_the_old_one(): void
    {
        Notification::fake();
        $position = $this->position();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])->assertOk();
        $old = $this->sentToken('amel@example.com');
        $invitation = PositionInvitation::query()->firstOrFail();

        Notification::fake();
        $this->actingAs($admin)->postJson('/organigramme/invitations/'.$invitation->id.'/renvoyer')->assertOk();
        $new = $this->sentToken('amel@example.com');

        $this->assertNotSame($old, $new);
        $this->assertSame(2, $invitation->fresh()->send_count);

        auth()->logout();
        $this->get('/invitation/'.$old)->assertNotFound();
        $this->get('/invitation/'.$new)->assertOk();
    }

    public function test_inviting_the_same_person_twice_reuses_the_invitation(): void
    {
        Notification::fake();
        $position = $this->position();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])->assertOk();
        $this->actingAs($admin)->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee('AMEL@example.com')]])->assertOk();

        $this->assertSame(1, PositionInvitation::query()->count());
        $this->assertSame(2, PositionInvitation::query()->first()->send_count);
    }

    public function test_revoking_closes_the_link(): void
    {
        Notification::fake();
        $position = $this->position();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])->assertOk();
        $token = $this->sentToken('amel@example.com');
        $invitation = PositionInvitation::query()->firstOrFail();

        $this->actingAs($admin)->deleteJson('/organigramme/invitations/'.$invitation->id)->assertOk();
        $this->assertSame('revoked', $invitation->fresh()->status());

        auth()->logout();
        $this->get('/invitation/'.$token)->assertOk()->assertSee('annulée')->assertDontSee('name="avatar"', false);
        $this->post('/invitation/'.$token, $this->registration())->assertSessionHasErrors('message');
        $this->assertSame(0, User::query()->where('email', 'amel@example.com')->count());
    }

    public function test_expired_invitation_is_refused(): void
    {
        $position = $this->position();
        PositionInvitation::query()->create(['node_id' => $position->id, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.com', 'token_hash' => PositionInvitation::hashToken(str_repeat('e', 40)), 'expires_at' => now()->subMinute()]);

        $this->get('/invitation/'.str_repeat('e', 40))->assertOk()->assertSee('expiré')->assertDontSee('name="avatar"', false);
        $this->post('/invitation/'.str_repeat('e', 40), $this->registration())->assertSessionHasErrors('message');
    }

    public function test_invited_guest_sees_prefilled_form_with_locked_email(): void
    {
        Notification::fake();
        $position = $this->position();

        $this->actingAs($this->admin())->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])->assertOk();
        $token = $this->sentToken('amel@example.com');
        auth()->logout();

        $this->get('/invitation/'.$token)
            ->assertOk()
            ->assertSee('Responsable communication')
            ->assertSee('value="amel@example.com"', false)
            ->assertSee('value="Amel"', false)
            ->assertSee('readonly', false)
            ->assertSee('/invitation/'.$token, false);
    }

    public function test_registration_through_the_invitation_creates_a_verified_account_and_a_pending_application(): void
    {
        Notification::fake();
        Storage::fake('public');
        $position = $this->position();

        $this->actingAs($this->admin())->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])->assertOk();
        $token = $this->sentToken('amel@example.com');
        auth()->logout();

        // Une autre adresse envoyée dans le formulaire est ignorée : celle de l'invitation fait foi.
        $this->post('/invitation/'.$token, $this->registration(['email' => 'autre@example.com']))->assertRedirect(route('home'));

        $user = User::query()->where('email', 'amel@example.com')->firstOrFail();
        $this->assertSame(0, User::query()->where('email', 'autre@example.com')->count());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('position_applications', ['node_id' => $position->id, 'user_id' => $user->id, 'status' => 'pending']);
        $this->assertSame(0, OrgAssignment::query()->where('node_id', $position->id)->count());

        $invitation = PositionInvitation::query()->firstOrFail();
        $this->assertSame('accepted', $invitation->status());
        $this->assertSame($user->id, $invitation->user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'position.invitation.accepted']);

        // Le lien ne peut plus servir une seconde fois.
        auth()->logout();
        $this->post('/invitation/'.$token, $this->registration(['first_name' => 'Autre']))->assertSessionHasErrors('message');
    }

    public function test_existing_account_is_sent_to_login_then_applies_with_a_message(): void
    {
        Notification::fake();
        $position = $this->position();
        $user = User::factory()->create(['email' => 'amel@example.com']);

        $this->actingAs($this->admin())->postJson('/organigramme/blocs/'.$position->id.'/invitations', ['invitees' => [$this->invitee()]])->assertOk();
        $token = $this->sentToken('amel@example.com');
        auth()->logout();

        $this->get('/invitation/'.$token)->assertRedirect(route('login'));
        $this->assertSame(url('/invitation/'.$token), session('url.intended'));

        $this->actingAs($user)->get('/invitation/'.$token)->assertOk()->assertSee('Pourquoi ce poste');
        $this->actingAs($user)->post('/invitation/'.$token, ['message' => 'Motivée.'])->assertRedirect(route('home'));

        $this->assertDatabaseHas('position_applications', ['node_id' => $position->id, 'user_id' => $user->id, 'status' => 'pending']);
        $this->assertSame('accepted', PositionInvitation::query()->firstOrFail()->status());
    }

    public function test_unknown_invitation_token_is_a_404(): void
    {
        $this->get('/invitation/'.str_repeat('z', 40))->assertNotFound();
        $this->post('/invitation/'.str_repeat('z', 40), [])->assertNotFound();
    }

    public function test_payload_lists_invitations_for_editors_only(): void
    {
        $position = $this->position();
        PositionInvitation::query()->create(['node_id' => $position->id, 'first_name' => 'Amel', 'last_name' => 'Ben Salah', 'email' => 'amel@example.com', 'token_hash' => PositionInvitation::hashToken('x'), 'expires_at' => now()->addDay(), 'last_sent_at' => now()]);

        $find = fn (array $json): ?array => collect($json['nodes'])->firstWhere('id', $position->id);

        $editor = $find($this->actingAs($this->admin())->getJson('/organigramme/donnees')->json());
        $this->assertSame('amel@example.com', $editor['invitations'][0]['email']);
        $this->assertSame('En attente', $editor['invitations'][0]['status_label']);

        $member = $find($this->actingAs(User::factory()->create())->getJson('/organigramme/donnees')->json());
        $this->assertEmpty($member['invitations'] ?? []);
    }

    public function test_application_count_is_untouched_when_the_invited_user_is_not_the_invitee(): void
    {
        $position = $this->position('multiple');
        PositionInvitation::query()->create(['node_id' => $position->id, 'first_name' => 'Amel', 'last_name' => 'B', 'email' => 'amel@example.com', 'token_hash' => PositionInvitation::hashToken(str_repeat('c', 40)), 'expires_at' => now()->addDay()]);
        $other = User::factory()->create(['email' => 'autre@example.com']);

        $this->actingAs($other)->post('/invitation/'.str_repeat('c', 40), ['message' => 'Moi aussi.'])->assertRedirect(route('home'));

        $this->assertSame(1, PositionApplication::query()->count());
        $this->assertSame('pending', PositionInvitation::query()->firstOrFail()->status());
    }
}
