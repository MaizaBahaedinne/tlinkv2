<?php

namespace Tests\Feature;

use App\Models\MailLog;
use App\Models\OrgNode;
use App\Models\PositionInvitation;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Notifications\PositionInvitationNotification;
use App\Support\MailTemplates;
use App\Support\PositionInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MailLogAndTemplatesTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    private function position(): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => OrgNode::query()->where('system_key', 'university-office')->value('id'),
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => 'single',
            'access_level' => 'none',
            'category' => 'national_office',
            'title' => 'Responsable communication',
            'sort_order' => 0,
        ]);
    }

    /** @return array<string, string> */
    private function template(array $override = []): array
    {
        return array_merge(MailTemplates::CATALOG['verification_code']['defaults'], $override);
    }

    public function test_every_mail_is_logged_per_address_without_its_content(): void
    {
        $member = User::factory()->create(['email' => 'amel@example.com']);

        $member->sendEmailVerificationNotification();

        $log = MailLog::query()->firstOrFail();
        $this->assertSame('amel@example.com', $log->to_email);
        $this->assertSame('sent', $log->status);
        $this->assertSame('verification_code', $log->template);
        $this->assertNotNull($log->sent_at);
        $this->assertStringContainsString('Votre code de confirmation', $log->subject);
        // Le code envoyé ne doit jamais être conservé dans le journal, pas même dans l'objet.
        $this->assertDoesNotMatchRegularExpression('/\d{6}/', $log->subject);
        $this->assertArrayNotHasKey('body', $log->getAttributes());
    }

    public function test_an_invitation_is_logged_with_its_template(): void
    {
        $invitation = PositionInvitation::query()->create(['node_id' => $this->position()->id, 'first_name' => 'Amel', 'last_name' => 'Ben Salah', 'email' => 'invite@example.com', 'token_hash' => PositionInvitation::hashToken('x'), 'expires_at' => now()->addDay()]);

        $this->assertTrue(PositionInvitations::deliver($invitation));

        $this->assertDatabaseHas('mail_logs', ['to_email' => 'invite@example.com', 'template' => 'position_invitation', 'status' => 'sent']);
    }

    public function test_a_failed_send_is_logged_with_its_error(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'scheme' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2]]);
        app('mail.manager')->purge('smtp');
        $invitation = PositionInvitation::query()->create(['node_id' => $this->position()->id, 'first_name' => 'Amel', 'last_name' => 'Ben Salah', 'email' => 'panne@example.com', 'token_hash' => PositionInvitation::hashToken('y'), 'expires_at' => now()->addDay()]);

        $this->assertFalse(PositionInvitations::deliver($invitation));

        $log = MailLog::query()->where('to_email', 'panne@example.com')->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertNotEmpty($log->error);
    }

    public function test_a_pending_send_older_than_five_minutes_counts_as_failed(): void
    {
        $fresh = MailLog::query()->create(['to_email' => 'a@example.com', 'status' => 'pending']);
        $stale = MailLog::query()->create(['to_email' => 'b@example.com', 'status' => 'pending']);
        $stale->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $this->assertSame('pending', $fresh->effectiveStatus());
        $this->assertSame('failed', $stale->fresh()->effectiveStatus());
    }

    public function test_the_log_page_groups_mails_by_address_and_drills_down(): void
    {
        MailLog::query()->create(['to_email' => 'amel@example.com', 'to_name' => 'Amel', 'subject' => 'Premier', 'status' => 'sent', 'template' => 'verification_code', 'sent_at' => now()]);
        MailLog::query()->create(['to_email' => 'amel@example.com', 'subject' => 'Second', 'status' => 'failed', 'error' => 'Connexion refusée']);
        MailLog::query()->create(['to_email' => 'karim@example.com', 'subject' => 'Autre', 'status' => 'sent', 'sent_at' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/messagerie-journal')->assertOk()
            ->assertSee('amel@example.com')
            ->assertSee('karim@example.com')
            ->assertDontSee('Connexion refusée');

        $this->actingAs($admin)->get('/admin/messagerie-journal?q=karim')->assertOk()->assertSee('karim@example.com')->assertDontSee('amel@example.com');

        $this->actingAs($admin)->get('/admin/messagerie-journal?email=amel@example.com')->assertOk()
            ->assertSee('Premier')->assertSee('Second')->assertSee('Connexion refusée')->assertDontSee('Autre');

        $this->actingAs($admin)->get('/admin/messagerie-journal?email=amel@example.com&status=failed')->assertOk()
            ->assertSee('Second')->assertDontSee('Premier');
    }

    public function test_only_global_admins_see_the_log_and_the_templates(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/messagerie-journal')->assertForbidden();
        $this->actingAs($member)->get('/admin/messagerie-modeles')->assertForbidden();
        $this->actingAs($member)->get('/admin/messagerie-modeles/verification_code')->assertForbidden();
        $this->actingAs($member)->put('/admin/messagerie-modeles/verification_code', $this->template())->assertForbidden();
    }

    public function test_the_templates_list_both_mails_and_the_editor_shows_a_preview(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/messagerie-modeles')->assertOk()
            ->assertSee('Code de confirmation de l’adresse e-mail', false)
            ->assertSee('Invitation à candidater à un poste');

        $this->actingAs($admin)->get('/admin/messagerie-modeles/position_invitation')->assertOk()
            ->assertSee('name="button_label"', false)
            ->assertSee('{poste}')
            ->assertSee('<iframe', false);

        $this->actingAs($admin)->get('/admin/messagerie-modeles/inconnu')->assertNotFound();
    }

    public function test_a_saved_template_is_used_by_the_real_mails(): void
    {
        $this->actingAs($this->admin())->put('/admin/messagerie-modeles/verification_code', $this->template([
            'subject' => 'Ton code {code} pour T.Link',
            'greeting' => 'Salut {prenom}',
            'body' => "Voici ton code :\n**{code}**\nValable {minutes} minutes.",
            'salutation' => 'Toute l’équipe',
        ]))->assertSessionHasNoErrors();

        $mail = (new EmailVerificationCode('123456'))->toMail(User::factory()->make(['first_name' => 'Amel']));

        $this->assertSame('Ton code 123456 pour T.Link', $mail->subject);
        $this->assertSame('Salut Amel', $mail->greeting);
        $this->assertContains('**123456**', $mail->introLines);
        $this->assertSame('Toute l’équipe', $mail->salutation);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.mail.template.updated']);
    }

    public function test_the_invitation_template_places_the_button_where_asked(): void
    {
        $this->actingAs($this->admin())->put('/admin/messagerie-modeles/position_invitation', array_merge(MailTemplates::CATALOG['position_invitation']['defaults'], [
            'body' => "Bonjour, un poste vous attend : {poste}.\n{bouton}\nÀ très vite.",
            'button_label' => 'Candidater maintenant',
        ]))->assertSessionHasNoErrors();

        $invitation = PositionInvitation::query()->create(['node_id' => $this->position()->id, 'first_name' => 'Amel', 'last_name' => 'Ben Salah', 'email' => 'a@example.com', 'token_hash' => PositionInvitation::hashToken('z'), 'expires_at' => now()->addDay()]);
        $mail = (new PositionInvitationNotification($invitation, str_repeat('t', 40)))->toMail(new \stdClass());

        $this->assertSame(['Bonjour, un poste vous attend : Responsable communication (University).'], $mail->introLines);
        $this->assertSame('Candidater maintenant', $mail->actionText);
        $this->assertSame(route('position.invitation', str_repeat('t', 40)), $mail->actionUrl);
        $this->assertSame(['À très vite.'], $mail->outroLines);
    }

    public function test_invalid_templates_are_refused(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/messagerie-modeles/verification_code', $this->template(['body' => 'Pas de code ici.']))
            ->assertSessionHasErrors('body');

        $this->actingAs($admin)->put('/admin/messagerie-modeles/verification_code', $this->template(['subject' => 'Bonjour {nom_inconnu} {code}']))
            ->assertSessionHasErrors('subject');

        $this->actingAs($admin)->put('/admin/messagerie-modeles/position_invitation', array_merge(MailTemplates::CATALOG['position_invitation']['defaults'], ['button_label' => '']))
            ->assertSessionHasErrors('button_label');

        $this->actingAs($admin)->put('/admin/messagerie-modeles/verification_code', $this->template(['subject' => '']))
            ->assertSessionHasErrors('subject');

        $this->assertFalse(MailTemplates::isCustomized('verification_code'));
    }

    public function test_resetting_restores_the_original_template(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/messagerie-modeles/verification_code', $this->template(['greeting' => 'Coucou {prenom}']))->assertSessionHasNoErrors();
        $this->assertTrue(MailTemplates::isCustomized('verification_code'));

        $this->actingAs($admin)->delete('/admin/messagerie-modeles/verification_code')->assertRedirect();

        $this->assertFalse(MailTemplates::isCustomized('verification_code'));
        $this->assertSame('Bienvenue {prenom} !', MailTemplates::get('verification_code')['greeting']);
    }

    public function test_the_preview_shows_the_draft_without_saving_it(): void
    {
        $this->actingAs($this->admin())->post('/admin/messagerie-modeles/verification_code/apercu', $this->template(['greeting' => 'Bonjour brouillon {prenom}']))
            ->assertOk()
            ->assertSee('Bonjour brouillon Amel')
            ->assertSee('482913');

        $this->assertFalse(MailTemplates::isCustomized('verification_code'));
    }

    public function test_official_mails_carry_the_tlink_logo_in_the_header_and_the_foundation_in_the_footer(): void
    {
        $html = (string) MailTemplates::sample('position_invitation')->render();

        $this->assertStringContainsString('/tlink-logo.png', $html);
        $this->assertStringContainsString('alt="T.Link"', $html);
        $this->assertStringContainsString('/logoFondation.png', $html);
        $this->assertStringContainsString('alt="Tunivisions Foundation"', $html);
        $this->assertStringContainsString('Fondation Tunivisions. Tous droits réservés.', $html);
        // L'en-tête précède le contenu et le pied le suit.
        $this->assertLessThan(strpos($html, 'Bonjour Amel'), strpos($html, '/tlink-logo.png'));
        $this->assertGreaterThan(strpos($html, 'Créer mon compte et candidater'), strpos($html, '/logoFondation.png'));
        // Texte du bouton de secours en français, et non plus en anglais.
        $this->assertStringContainsString('ne fonctionne pas', $html);
        $this->assertStringNotContainsString('Laravel', $html);
        $this->assertFileExists(public_path('tlink-logo.png'));
        $this->assertFileExists(public_path('logoFondation.png'));
    }

    public function test_the_plain_text_version_is_branded_too(): void
    {
        $message = MailTemplates::sample('position_invitation');
        $text = (string) app(\Illuminate\Mail\Markdown::class)->renderText('notifications::email', $message->data());

        $this->assertStringContainsString('Fondation Tunivisions', $text);
        $this->assertStringNotContainsString('Laravel', $text);
    }

    public function test_a_test_message_is_sent_with_sample_values_and_logged(): void
    {
        $this->actingAs($this->admin())->post('/admin/messagerie-modeles/position_invitation/test', ['to' => 'test@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $log = MailLog::query()->where('to_email', 'test@example.com')->firstOrFail();
        $this->assertStringStartsWith('[TEST]', $log->subject);
        $this->assertSame('position_invitation', $log->template);
        $this->assertSame('sent', $log->status);
    }
}
