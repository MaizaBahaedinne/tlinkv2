<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'enabled' => 1,
            'host' => 'ssl0.ovh.net',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'no-reply@tunivisionsfoundation.org',
            'password' => 'S3cret-pass!',
            'from_address' => 'no-reply@tunivisionsfoundation.org',
            'from_name' => 'T.Link',
        ], $override);
    }

    public function test_the_page_lists_every_application(): void
    {
        $this->actingAs($this->admin())->get('/admin/messagerie')->assertOk()
            ->assertSee('Messagerie (e-mails envoyés)')
            ->assertSee('T.Link')
            ->assertSee('Tunivisions Academy')
            ->assertSee('Tunivisions Stores')
            ->assertSee('Chapitres')
            ->assertSee('Projets')
            ->assertSee('ssl0.ovh.net');
    }

    public function test_saving_a_mailbox_encrypts_the_password_and_never_shows_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/messagerie/tlink', $this->payload())->assertRedirect()->assertSessionHasNoErrors();

        $stored = Setting::query()->where('group', 'mail')->where('key', 'profiles')->value('value');
        $this->assertStringNotContainsString('S3cret-pass!', $stored);
        $this->assertSame('S3cret-pass!', MailSettings::profile('tlink')['password']);
        $this->assertTrue(MailSettings::usable('tlink'));

        $this->actingAs($admin)->get('/admin/messagerie')->assertOk()->assertDontSee('S3cret-pass!')->assertSee('no-reply@tunivisionsfoundation.org');

        $log = \App\Models\AuditLog::query()->where('action', 'admin.mail.updated')->firstOrFail();
        $this->assertStringNotContainsString('S3cret-pass!', json_encode($log->metadata));
    }

    public function test_an_empty_password_keeps_the_saved_one(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/messagerie/tlink', $this->payload());

        $this->actingAs($admin)->put('/admin/messagerie/tlink', $this->payload(['password' => '', 'from_name' => 'Autre nom']))->assertSessionHasNoErrors();

        $this->assertSame('S3cret-pass!', MailSettings::profile('tlink')['password']);
        $this->assertSame('Autre nom', MailSettings::profile('tlink')['from_name']);
    }

    public function test_activating_without_any_password_is_refused(): void
    {
        $this->actingAs($this->admin())->put('/admin/messagerie/tlink', $this->payload(['password' => '']))->assertSessionHasErrors('password');

        $this->assertFalse(MailSettings::usable('tlink'));
    }

    public function test_the_server_name_is_validated(): void
    {
        $this->actingAs($this->admin())->put('/admin/messagerie/tlink', $this->payload(['host' => 'http://evil.test:25/x']))->assertSessionHasErrors('host');
        $this->actingAs($this->admin())->put('/admin/messagerie/tlink', $this->payload(['port' => 70000]))->assertSessionHasErrors('port');
        $this->actingAs($this->admin())->put('/admin/messagerie/tlink', $this->payload(['from_address' => 'pas-un-email']))->assertSessionHasErrors('from_address');
        $this->actingAs($this->admin())->put('/admin/messagerie/inconnue', $this->payload())->assertNotFound();
    }

    public function test_the_tlink_mailbox_becomes_the_default_sender(): void
    {
        $this->actingAs($this->admin())->put('/admin/messagerie/tlink', $this->payload(['encryption' => 'ssl', 'port' => 465]));

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('ssl0.ovh.net', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('no-reply@tunivisionsfoundation.org', config('mail.from.address'));
        $this->assertSame('T.Link', config('mail.from.name'));
    }

    public function test_starttls_uses_the_smtp_scheme(): void
    {
        $this->actingAs($this->admin())->put('/admin/messagerie/tlink', $this->payload(['encryption' => 'tls', 'port' => 587]));

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
    }

    public function test_another_application_gets_its_own_mailer_and_falls_back_to_tlink_until_then(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/messagerie/tlink', $this->payload());

        $this->assertSame('smtp', MailSettings::mailer('academy'));
        $this->assertSame('no-reply@tunivisionsfoundation.org', MailSettings::from('academy')[0]);

        $this->actingAs($admin)->put('/admin/messagerie/academy', $this->payload(['username' => 'academy@tunivisionsfoundation.org', 'from_address' => 'academy@tunivisionsfoundation.org', 'from_name' => 'Academy']));
        MailSettings::apply();

        $this->assertSame('app-academy', MailSettings::mailer('academy'));
        $this->assertSame(['academy@tunivisionsfoundation.org', 'Academy'], MailSettings::from('academy'));
        $this->assertSame('academy@tunivisionsfoundation.org', config('mail.mailers.app-academy.username'));
        $this->assertSame('no-reply@tunivisionsfoundation.org', config('mail.from.address'));
    }

    public function test_nothing_changes_without_a_saved_mailbox(): void
    {
        $before = config('mail.default');

        MailSettings::apply();

        $this->assertSame($before, config('mail.default'));
        $this->assertNull(MailSettings::mailer('tlink'));
    }

    public function test_a_test_message_needs_a_saved_mailbox_and_reports_failures_without_the_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/messagerie/tlink/test', ['to' => 'moi@exemple.com'])->assertSessionHasErrors('test');

        // Serveur injoignable : l'échec est affiché, sans fuite du mot de passe.
        $this->actingAs($admin)->put('/admin/messagerie/tlink', $this->payload(['host' => 'localhost', 'port' => 1]));
        $response = $this->actingAs($admin)->post('/admin/messagerie/tlink/test', ['to' => 'moi@exemple.com']);

        $response->assertSessionHasErrors('test');
        $this->assertStringNotContainsString('S3cret-pass!', (string) session('errors')->first('test'));
        $this->assertStringContainsString('Échec de l’envoi', (string) session('errors')->first('test'));
    }

    public function test_only_global_admins_can_open_or_change_it(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/messagerie')->assertForbidden();
        $this->actingAs($member)->put('/admin/messagerie/tlink', $this->payload())->assertForbidden();
        $this->actingAs($member)->post('/admin/messagerie/tlink/test', ['to' => 'a@b.co'])->assertForbidden();
        $this->assertFalse(MailSettings::usable('tlink'));
    }
}
