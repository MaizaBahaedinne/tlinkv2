<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Governorate;
use App\Models\OrgNode;
use App\Models\User;
use App\Models\WebmailContact;
use App\Models\WebmailLabel;
use App\Models\WebmailMailbox;
use App\Models\WebmailMailboxUser;
use App\Models\WebmailMessageLabel;
use App\Support\ModulePermissions;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\Webmail\GatewayFactory;
use App\Support\Webmail\HtmlSanitizer;
use App\Support\Webmail\ImapGateway;
use App\Support\Webmail\OvhApi;
use App\Support\Webmail\WebmailAccess;
use App\Support\Webmail\WebmailSettings;
use App\Support\Webmail\WebmailSync;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\GrantsAccess;
use Tests\Support\FakeGatewayFactory;
use Tests\Support\FakeMailGateway;
use Tests\TestCase;

/** Webmail : boîtes partagées par entité (OVH), lecture, envoi, étiquettes, contacts, notifications. */
class WebmailTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private OrgNode $club;

    private OrgNode $otherClub;

    private User $president;

    private User $otherPresident;

    private User $admin;

    private WebmailMailbox $mailbox;

    private FakeMailGateway $gateway;

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
        $this->grantTo($this->club, $this->president, ['webmail.inbox' => ['read', 'create', 'update', 'delete'], 'webmail.mailboxes' => ['read', 'create', 'update', 'delete']]);
        $this->otherPresident = User::factory()->create();
        $this->grantTo($this->otherClub, $this->otherPresident, ['webmail.inbox' => ['read']]);

        $this->admin = User::factory()->create();
        $this->grantPlatformAdmin($this->admin);

        $this->gateway = new FakeMailGateway();
        $this->gateway->messages['INBOX'] = [FakeMailGateway::mail(7, 'Demande de partenariat')];
        $this->app->instance(GatewayFactory::class, new FakeGatewayFactory($this->gateway));

        WebmailSettings::save([
            'endpoint' => 'ovh-eu', 'application_key' => 'AK123', 'application_secret' => 'AS-secret-456', 'consumer_key' => 'CK-secret-789',
            'domain' => 'fondation.test', 'imap_host' => 'ssl0.ovh.net', 'imap_port' => 993, 'smtp_host' => 'ssl0.ovh.net', 'smtp_port' => 465, 'mailbox_size' => null,
        ]);

        $this->mailbox = WebmailMailbox::query()->create(['club_id' => $this->club->id, 'address' => 'esprit@fondation.test', 'password' => 'Mot2Passe-Secret', 'display_name' => 'Club ESPRIT']);
    }

    private function grantTo(OrgNode $club, User $user, array $matrix): void
    {
        $node = OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'president')->firstOrFail();
        ModulePermissions::apply($node, $matrix, false);
        $this->placeOn($node, $user);
    }

    public function test_connection_settings_are_global_only_and_secrets_are_encrypted(): void
    {
        $this->actingAs($this->president)->get(route('admin.webmail.settings.edit'))->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->admin)->get(route('admin.webmail.settings.edit'))->assertOk()->assertDontSee('AS-secret-456')->assertDontSee('CK-secret-789')->assertSee('fondation.test');

        $this->put(route('admin.webmail.settings.update'), [
            'endpoint' => 'ovh-eu', 'application_key' => 'AK999', 'application_secret' => '', 'consumer_key' => '', 'domain' => 'tunivisions.org',
            'imap_host' => 'ssl0.ovh.net', 'imap_port' => 993, 'smtp_host' => 'ssl0.ovh.net', 'smtp_port' => 465,
        ])->assertRedirect();

        $raw = (string) \App\Models\Setting::query()->where('group', 'webmail')->value('value');
        $this->assertStringNotContainsString('AS-secret-456', $raw);
        $this->assertSame('AS-secret-456', WebmailSettings::get()['application_secret']);
        $this->assertSame('tunivisions.org', WebmailSettings::domain());
        $this->assertTrue(AuditLog::query()->where('action', 'admin.webmail.settings.updated')->exists());
    }

    public function test_mailbox_is_created_at_ovh_with_a_generated_password(): void
    {
        Http::fake([
            '*/auth/time' => Http::response('1700000000'),
            '*/email/domain/fondation.test/account' => Http::response(['id' => 1]),
        ]);

        $this->actingAs($this->president)->post(route('admin.webmail.mailboxes.store'), [
            'entity' => 'club:'.$this->club->id, 'local_part' => 'tresorier', 'mode' => 'ovh', 'display_name' => 'Trésorerie',
        ])->assertRedirect(route('admin.webmail.mailboxes.index'));

        $mailbox = WebmailMailbox::query()->where('address', 'tresorier@fondation.test')->firstOrFail();
        $this->assertTrue($mailbox->provisioned);
        $this->assertSame(24, strlen($mailbox->password));
        $this->assertStringNotContainsString($mailbox->password, (string) \Illuminate\Support\Facades\DB::table('webmail_mailboxes')->where('id', $mailbox->id)->value('password'));

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/email/domain/fondation.test/account')) {
                return false;
            }

            return $request->method() === 'POST'
                && $request['accountName'] === 'tresorier'
                && str_starts_with($request->header('X-Ovh-Signature')[0] ?? '', '$1$')
                && $request->header('X-Ovh-Application')[0] === 'AK123'
                && $request->header('X-Ovh-Consumer')[0] === 'CK-secret-789';
        });

        $this->get(route('admin.webmail.mailboxes.index'))->assertOk()->assertSee('tresorier@fondation.test')->assertDontSee($mailbox->password);
        $this->assertFalse(collect(AuditLog::query()->where('action', 'admin.webmail.mailbox.created')->get())->contains(fn ($log) => str_contains(json_encode($log->metadata), $mailbox->password)));
    }

    public function test_ovh_refusal_creates_nothing_and_existing_mailbox_needs_its_password(): void
    {
        Http::fake([
            '*/auth/time' => Http::response('1700000000'),
            '*/email/domain/fondation.test/account' => Http::response(['message' => 'Account already exists'], 409),
        ]);

        $this->actingAs($this->president)->post(route('admin.webmail.mailboxes.store'), ['entity' => 'club:'.$this->club->id, 'local_part' => 'doublon', 'mode' => 'ovh'])
            ->assertSessionHasErrors('mode');
        $this->assertFalse(WebmailMailbox::query()->where('address', 'doublon@fondation.test')->exists());

        $this->post(route('admin.webmail.mailboxes.store'), ['entity' => 'club:'.$this->club->id, 'local_part' => 'ancienne', 'mode' => 'existing'])->assertSessionHasErrors('password');
        $this->post(route('admin.webmail.mailboxes.store'), ['entity' => 'club:'.$this->club->id, 'local_part' => 'ancienne', 'mode' => 'existing', 'password' => 'Existant-1234'])->assertRedirect();
        $this->assertFalse(WebmailMailbox::query()->where('address', 'ancienne@fondation.test')->firstOrFail()->provisioned);

        // Une entité hors périmètre est refusée.
        $this->post(route('admin.webmail.mailboxes.store'), ['entity' => 'club:'.$this->otherClub->id, 'local_part' => 'enit', 'mode' => 'existing', 'password' => 'Existant-1234'])->assertSessionHasErrors('entity');
    }

    public function test_password_reset_for_provisioned_mailbox_changes_it_at_ovh(): void
    {
        Http::fake(['*/auth/time' => Http::response('1700000000'), '*' => Http::response([])]);
        $this->mailbox->update(['provisioned' => true]);

        $this->actingAs($this->president)->post(route('admin.webmail.mailboxes.password', $this->mailbox))->assertRedirect();

        $this->assertNotSame('Mot2Passe-Secret', $this->mailbox->fresh()->password);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/account/esprit/changePassword'));
    }

    public function test_only_the_entity_members_with_the_right_see_a_mailbox(): void
    {
        $this->actingAs($this->president)->get(route('webmail.index'))->assertOk()->assertSee('Demande de partenariat')->assertSee('esprit@fondation.test');

        $this->flushSession();
        $this->actingAs($this->otherPresident)->get(route('webmail.index'))->assertOk()->assertDontSee('esprit@fondation.test')->assertSee('Aucune boîte à lire');
        $this->get(route('webmail.show', ['mailbox' => $this->mailbox, 'uid' => 7]))->assertForbidden();
        $this->get(route('webmail.compose', $this->mailbox))->assertForbidden();
        $this->get(route('admin.webmail.mailboxes.index'))->assertForbidden();
    }

    public function test_reading_a_message_blocks_active_content_and_marks_it_read(): void
    {
        $response = $this->actingAs($this->president)->get(route('webmail.show', ['mailbox' => $this->mailbox, 'uid' => 7, 'dossier' => 'INBOX']))->assertOk();

        $response->assertSee('sandbox=', false)->assertSee('Bonjour')->assertDontSee('pixel.example', false)->assertDontSee('javascript:', false)->assertDontSee('onclick', false);
        $this->assertStringNotContainsString('&lt;script', $response->getContent());
        $this->assertContains(['INBOX', 7, 'seen', true], $this->gateway->marks);
    }

    public function test_html_sanitizer_removes_dangerous_content(): void
    {
        $clean = HtmlSanitizer::clean('<div onmouseover="x()"><script>alert(1)</script><iframe src="https://evil"></iframe><a href="javascript:alert(1)">a</a><a href="https://ok.test">b</a><img src="https://t.test/p.gif"><img src="data:image/png;base64,AAAA"><form><input></form><p style="background:url(http://x)">t</p></div>');

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('t.test', $clean);
        $this->assertStringNotContainsString('<form', $clean);
        $this->assertStringNotContainsString('url(', $clean);
        $this->assertStringContainsString('href="https://ok.test"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
        $this->assertStringContainsString('data:image/png;base64,AAAA', $clean);
    }

    public function test_sending_a_message_validates_addresses_and_saves_contacts(): void
    {
        $this->actingAs($this->president)->post(route('webmail.send', $this->mailbox), ['to' => 'pas-une-adresse', 'subject' => 'Hello', 'body' => 'x'])->assertSessionHasErrors('to');
        $this->post(route('webmail.send', $this->mailbox), ['subject' => 'Hello', 'body' => 'x', 'action' => 'send'])->assertSessionHasErrors('to');
        $this->assertSame([], $this->gateway->sent);

        $this->post(route('webmail.send', $this->mailbox), [
            'to' => 'Marie Ben <marie@example.org>, paul@example.org', 'cc' => 'chef@example.org', 'subject' => "Réunion\r\nBcc: evil@example.org", 'body' => 'Bonjour',
            'in_reply_to' => '<abc@example.org>', 'reply_uid' => 7, 'reply_folder' => 'INBOX', 'draft_uid' => 3,
            'attachments' => [UploadedFile::fake()->create('devis.pdf', 20, 'application/pdf')],
        ])->assertRedirect(route('webmail.index', ['boite' => $this->mailbox->id]));

        $this->assertCount(1, $this->gateway->sent);
        $sent = $this->gateway->sent[0];
        $this->assertSame(['"Marie Ben" <marie@example.org>', 'paul@example.org'], $sent['to']);
        $this->assertStringNotContainsString("\n", $sent['subject']);
        $this->assertSame('devis.pdf', $sent['attachments'][0]['name']);
        $this->assertContains(['INBOX', 7, 'answered', true], $this->gateway->marks);
        $this->assertContains(['Drafts', 3], $this->gateway->trashed);
        $this->assertSame(3, WebmailContact::query()->where('mailbox_id', $this->mailbox->id)->count());
        $this->assertSame('Marie Ben', WebmailContact::query()->where('email', 'marie@example.org')->value('name'));
        $this->assertTrue(AuditLog::query()->where('action', 'webmail.message.sent')->exists());
    }

    public function test_draft_is_saved_without_recipient_and_read_only_cannot_write(): void
    {
        $this->actingAs($this->president)->post(route('webmail.send', $this->mailbox), ['action' => 'draft', 'subject' => 'Brouillon', 'body' => 'à finir', 'draft_uid' => 4])->assertRedirect();
        $this->assertCount(1, $this->gateway->drafts);
        $this->assertSame(4, $this->gateway->drafts[0][1]);
        $this->assertSame([], $this->gateway->sent);

        // Poste en lecture seule sur sa propre boîte.
        $reader = User::factory()->create();
        $this->grantTo($this->club, $reader, ['webmail.inbox' => ['read']]);
        $this->flushSession();
        $this->actingAs($reader)->get(route('webmail.index'))->assertOk()->assertSee('Demande de partenariat');
        $this->post(route('webmail.send', $this->mailbox), ['to' => 'a@example.org', 'body' => 'x'])->assertForbidden();
        $this->delete(route('webmail.trash', ['mailbox' => $this->mailbox, 'uid' => 7]))->assertForbidden();
        $this->post(route('webmail.mark', ['mailbox' => $this->mailbox, 'uid' => 7]), ['flag' => 'flagged', 'on' => 1])->assertForbidden();
    }

    public function test_reply_prefills_recipient_subject_and_quotation(): void
    {
        $this->actingAs($this->president)->get(route('webmail.compose', ['mailbox' => $this->mailbox, 'mode' => 'reply', 'uid' => 7, 'dossier' => 'INBOX']))
            ->assertOk()->assertSee('Jean &lt;jean@example.org&gt;', false)->assertSee('Re: Demande de partenariat')->assertSee('&gt; Bonjour', false);

        $this->get(route('webmail.compose', ['mailbox' => $this->mailbox, 'mode' => 'forward', 'uid' => 7, 'dossier' => 'INBOX']))->assertOk()->assertSee('Tr: Demande de partenariat');
    }

    public function test_trash_move_and_attachment_download(): void
    {
        $this->actingAs($this->president)->delete(route('webmail.trash', ['mailbox' => $this->mailbox, 'uid' => 7, 'dossier' => 'INBOX']))->assertRedirect();
        $this->assertContains(['INBOX', 7], $this->gateway->trashed);

        $this->post(route('webmail.move', ['mailbox' => $this->mailbox, 'uid' => 7]), ['dossier' => 'INBOX', 'target' => 'Trash'])->assertRedirect();
        $this->post(route('webmail.move', ['mailbox' => $this->mailbox, 'uid' => 7]), ['dossier' => 'INBOX', 'target' => 'Inconnu'])->assertStatus(422);
        $this->assertSame([['INBOX', 7, 'Trash']], $this->gateway->moved);

        $this->get(route('webmail.attachment', ['mailbox' => $this->mailbox, 'uid' => 7, 'index' => 0, 'dossier' => 'INBOX']))
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_labels_contacts_and_signature(): void
    {
        $this->actingAs($this->president)->post(route('webmail.labels.store', $this->mailbox), ['name' => 'Urgent', 'color' => 'red'])->assertRedirect();
        $this->post(route('webmail.labels.store', $this->mailbox), ['name' => 'Urgent', 'color' => 'red'])->assertSessionHasErrors('name');
        $label = WebmailLabel::query()->firstOrFail();

        $this->post(route('webmail.tag', ['mailbox' => $this->mailbox, 'uid' => 7]), ['dossier' => 'INBOX', 'labels' => [$label->id, 9999]])->assertRedirect();
        $this->assertSame(1, WebmailMessageLabel::query()->count());

        $this->get(route('webmail.index', ['boite' => $this->mailbox->id, 'etiquette' => $label->id]))->assertOk()->assertSee('Demande de partenariat');
        $this->get(route('webmail.index', ['boite' => $this->mailbox->id, 'q' => 'inexistant']))->assertOk()->assertSee('Aucun message ne correspond');

        $this->post(route('webmail.contacts.store', $this->mailbox), ['name' => 'Sponsor', 'email' => 'SPONSOR@Example.org'])->assertRedirect();
        $this->assertTrue(WebmailContact::query()->where('email', 'sponsor@example.org')->exists());
        $this->put(route('webmail.signature', $this->mailbox), ['signature' => 'Cordialement, Club ESPRIT'])->assertRedirect();
        $this->assertSame('Cordialement, Club ESPRIT', $this->mailbox->fresh()->signature);
        $this->get(route('webmail.settings', $this->mailbox))->assertOk()->assertSee('Urgent')->assertSee('sponsor@example.org');

        $this->delete(route('webmail.labels.destroy', ['mailbox' => $this->mailbox, 'label' => $label]))->assertRedirect();
        $this->assertSame(0, WebmailMessageLabel::query()->count());
    }

    public function test_unreachable_mailbox_shows_an_error_instead_of_crashing(): void
    {
        $this->gateway->down = true;

        $this->actingAs($this->president)->get(route('webmail.index'))->assertOk()->assertSee('Connexion à la boîte impossible.');
    }

    public function test_sync_sets_a_baseline_then_notifies_readers_of_new_messages(): void
    {
        $this->gateway->unseen = 1;

        $first = WebmailSync::run();
        $this->assertSame(1, $first['checked']);
        $this->assertSame(0, $first['notified']);
        $this->assertSame(7, (int) $this->mailbox->fresh()->last_uid);
        $this->assertSame(1, $this->mailbox->fresh()->unseen);

        $this->gateway->messages['INBOX'][] = FakeMailGateway::mail(8, 'Invitation au forum', 'forum@example.org');
        $this->gateway->unseen = 2;

        $second = WebmailSync::run();
        $this->assertSame(1, $second['notified']);

        $notification = AppNotification::query()->where('user_id', $this->president->id)->where('type', 'webmail')->firstOrFail();
        $this->assertSame('/webmail?boite='.$this->mailbox->id, $notification->data['url']);
        $this->assertStringContainsString('Invitation au forum', $notification->body);
        $this->assertFalse(AppNotification::query()->where('user_id', $this->otherPresident->id)->where('type', 'webmail')->exists());

        $this->gateway->down = true;
        $third = WebmailSync::run();
        $this->assertSame(1, $third['failed']);
        $this->assertNotNull($this->mailbox->fresh()->last_error);
    }

    public function test_generated_passwords_follow_ovh_rules(): void
    {
        $password = OvhApi::generatePassword();

        $this->assertSame(24, strlen($password));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $password);
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
    }

    public function test_outgoing_message_carries_signature_threading_and_attachments(): void
    {
        $this->mailbox->update(['signature' => 'Le bureau du club']);
        $path = tempnam(sys_get_temp_dir(), 'wm');
        file_put_contents($path, 'contenu');

        $method = new \ReflectionMethod(ImapGateway::class, 'email');
        $email = $method->invoke(new ImapGateway($this->mailbox->fresh()), [
            'to' => ['marie@example.org'], 'cc' => ['"Chef" <chef@example.org>'], 'subject' => 'Re: Sujet', 'body' => 'Bonjour',
            'in_reply_to' => '<abc@example.org>', 'references' => '<old@example.org>', 'attachments' => [['path' => $path, 'name' => 'note.txt', 'mime' => 'text/plain']],
        ]);

        $raw = $email->toString();
        $this->assertStringContainsString('Le bureau du club', $raw);
        $this->assertStringContainsString('In-Reply-To: <abc@example.org>', $raw);
        $this->assertStringContainsString('References: <old@example.org> <abc@example.org>', $raw);
        $this->assertStringContainsString('note.txt', $raw);
        $this->assertStringContainsString('esprit@fondation.test', $raw);
        @unlink($path);
    }

    public function test_mailbox_page_lists_authorized_accounts_with_their_rights(): void
    {
        $this->assertSame(['read', 'create', 'update', 'delete'], \App\Support\Webmail\WebmailAccess::authorized($this->mailbox->refresh())->firstWhere(fn (array $row): bool => $row['user']->is($this->president))['actions']);

        // Le poste du club est partagé : sa matrice passe en lecture seule pour ses deux titulaires.
        $reader = User::factory()->create(['name' => 'Lecteur Esprit']);
        $this->grantTo($this->club, $reader, ['webmail.inbox' => ['read']]);

        $page = $this->actingAs($this->admin)->get(route('admin.webmail.mailboxes.edit', $this->mailbox))->assertOk()
            ->assertSee('Comptes autorisés à utiliser cette boîte')->assertSee($this->president->name)->assertSee('Lecteur Esprit')->assertSee('Ouvrir dans T.Link Webmail');

        $this->assertStringNotContainsString($this->otherPresident->name, $page->getContent());

        $authorized = \App\Support\Webmail\WebmailAccess::authorized($this->mailbox->refresh())->keyBy(fn (array $row): int => $row['user']->id);
        $this->assertSame(['read'], $authorized[$this->president->id]['actions']);
        $this->assertSame(['read'], $authorized[$reader->id]['actions']);
        $this->assertArrayNotHasKey($this->otherPresident->id, $authorized->all());
    }

    public function test_webmail_is_its_own_application_outside_the_admin(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.webmail.mail.index'));
        $this->assertSame('webmail', \App\Support\Domains::sectionOfRoute('webmail.index'));
        $this->assertSame('webmail', \App\Support\Domains::sectionOfPath('/webmail'));

        $this->actingAs($this->president)->get(route('admin.webmail.mailboxes.index'))->assertOk()->assertDontSee('Boîtes de réception');

        $this->assertTrue(\App\Support\Webmail\WebmailAccess::available($this->president));
        request()->attributes->remove('webmail.readable.'.$this->otherPresident->id);
        $this->assertFalse(\App\Support\Webmail\WebmailAccess::available($this->otherPresident));
        $this->assertFalse(\App\Support\Webmail\WebmailAccess::available(User::factory()->create()));
    }

    public function test_accounts_are_chosen_by_search_and_get_their_own_rights(): void
    {
        $member = User::factory()->create(['name' => 'Salma Ben Youssef', 'email' => 'salma@example.org']);
        $inactive = User::factory()->create(['name' => 'Salma Inactive', 'is_active' => false]);

        $this->actingAs($this->president)->getJson(route('admin.webmail.mailboxes.users.search', [$this->mailbox, 'q' => 'salma']))
            ->assertOk()->assertJsonCount(1, 'users')->assertJsonPath('users.0.id', $member->id)->assertJsonMissing(['id' => $inactive->id]);
        $this->getJson(route('admin.webmail.mailboxes.users.search', [$this->mailbox, 'q' => 's']))->assertOk()->assertJson(['users' => []]);

        // Sans poste ni droit manuel : aucune boîte.
        $this->flushSession();
        $this->actingAs($member)->get(route('webmail.index'))->assertOk()->assertSee('Aucune boîte à lire');
        $this->get(route('webmail.show', ['mailbox' => $this->mailbox, 'uid' => 7]))->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->president)->post(route('admin.webmail.mailboxes.users.store', $this->mailbox), ['user_id' => $member->id, 'actions' => ['create']])
            ->assertRedirect(route('admin.webmail.mailboxes.edit', $this->mailbox));
        $this->assertSame(['read', 'create'], WebmailMailboxUser::query()->where('user_id', $member->id)->firstOrFail()->actions);
        $this->get(route('admin.webmail.mailboxes.edit', $this->mailbox))->assertOk()->assertSee('Salma Ben Youssef')->assertSee('Choisi ici');
        $this->getJson(route('admin.webmail.mailboxes.users.search', [$this->mailbox, 'q' => 'salma']))->assertJson(['users' => []]);
        $this->assertTrue(AuditLog::query()->where('action', 'admin.webmail.access.granted')->exists());

        // Elle lit et écrit, mais ne classe ni ne supprime.
        $this->flushSession();
        $this->actingAs($member)->get(route('webmail.index'))->assertOk()->assertSee('Demande de partenariat')->assertSee('esprit@fondation.test');
        $this->get(route('webmail.show', ['mailbox' => $this->mailbox, 'uid' => 7]))->assertOk();
        $this->get(route('webmail.compose', $this->mailbox))->assertOk();
        $this->post(route('webmail.mark', ['mailbox' => $this->mailbox, 'uid' => 7]), ['flag' => 'flagged', 'on' => 1])->assertForbidden();
        $this->delete(route('webmail.trash', ['mailbox' => $this->mailbox, 'uid' => 7]))->assertForbidden();
        $this->assertTrue(WebmailAccess::available($member));
        $this->get(route('admin.webmail.mailboxes.edit', $this->mailbox))->assertForbidden();

        // Droits modifiés puis retirés.
        $this->flushSession();
        $this->actingAs($this->president)->put(route('admin.webmail.mailboxes.users.update', [$this->mailbox, $member]), ['actions' => ['update', 'delete']])->assertRedirect();
        $this->assertSame(['read', 'update', 'delete'], WebmailMailboxUser::query()->where('user_id', $member->id)->firstOrFail()->actions);

        $this->delete(route('admin.webmail.mailboxes.users.destroy', [$this->mailbox, $member]))->assertRedirect();
        $this->assertSame(0, WebmailMailboxUser::query()->count());
        $this->flushSession();
        $this->actingAs($member)->get(route('webmail.index'))->assertOk()->assertSee('Aucune boîte à lire');
    }

    public function test_manually_chosen_accounts_are_managed_only_by_those_who_manage_the_mailbox(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->otherPresident)->post(route('admin.webmail.mailboxes.users.store', $this->mailbox), ['user_id' => $member->id])->assertForbidden();
        $this->getJson(route('admin.webmail.mailboxes.users.search', [$this->mailbox, 'q' => 'ab']))->assertForbidden();
        $this->assertSame(0, WebmailMailboxUser::query()->count());

        $this->flushSession();
        $this->actingAs($this->president)->post(route('admin.webmail.mailboxes.users.store', $this->mailbox), ['user_id' => 999999])->assertSessionHasErrors('user_id');
    }

    public function test_manually_chosen_accounts_are_notified_of_new_messages(): void
    {
        $member = User::factory()->create();
        WebmailMailboxUser::query()->create(['mailbox_id' => $this->mailbox->id, 'user_id' => $member->id, 'actions' => ['read']]);

        $this->assertContains($member->id, WebmailAccess::readers($this->mailbox));
    }

    public function test_signature_accepts_long_text_and_html_which_is_sanitised_and_sent_as_html(): void
    {
        $long = str_repeat("Cordialement, le bureau du club ESPRIT.\n", 2000);
        $this->assertGreaterThan(65535, strlen($long));

        $this->actingAs($this->president)->put(route('admin.webmail.mailboxes.update', $this->mailbox), ['signature' => $long, 'is_active' => 1])->assertRedirect();
        $this->assertSame(trim($long), $this->mailbox->fresh()->signature);

        $html = '<p><strong>Le bureau</strong><script>alert(1)</script></p><a href="javascript:x()" onclick="y()">site</a> <a href="https://esprit.example">ESPRIT</a><img src="https://esprit.example/logo.png" onerror="z()">';
        $this->put(route('webmail.signature', $this->mailbox), ['signature' => $html])->assertRedirect();

        $saved = (string) $this->mailbox->fresh()->signature;
        $this->assertStringContainsString('<strong>Le bureau</strong>', $saved);
        $this->assertStringContainsString('https://esprit.example/logo.png', $saved);
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('javascript:', $saved);
        $this->assertStringNotContainsString('onclick', $saved);
        $this->assertStringNotContainsString('onerror', $saved);

        $method = new \ReflectionMethod(ImapGateway::class, 'email');
        $raw = $method->invoke(new ImapGateway($this->mailbox->fresh()), ['to' => ['marie@example.org'], 'subject' => 'Sujet', 'body' => 'Bonjour'])->toString();

        $this->assertStringContainsString('text/html', $raw);
        $this->assertStringContainsString('<strong>Le bureau</strong>', quoted_printable_decode($raw));
        $this->assertStringContainsString('-- ', quoted_printable_decode($raw));

        $this->get(route('webmail.settings', $this->mailbox))->assertOk()->assertSee('Aperçu');
        $this->get(route('admin.webmail.mailboxes.edit', $this->mailbox))->assertOk()->assertSee('signature-preview', false);
    }

    public function test_deleting_a_mailbox_at_ovh_only_when_asked(): void
    {
        Http::fake(['*/auth/time' => Http::response('1700000000'), '*' => Http::response([])]);
        $this->mailbox->update(['provisioned' => true]);

        $this->actingAs($this->president)->delete(route('admin.webmail.mailboxes.destroy', $this->mailbox))->assertRedirect();
        Http::assertNotSent(fn ($request): bool => $request->method() === 'DELETE');
        $this->assertFalse(WebmailMailbox::query()->whereKey($this->mailbox->id)->exists());

        $other = WebmailMailbox::query()->create(['club_id' => $this->club->id, 'address' => 'tmp@fondation.test', 'password' => 'Mot2Passe-Secret', 'provisioned' => true]);
        $this->delete(route('admin.webmail.mailboxes.destroy', $other), ['ovh' => 1])->assertRedirect();
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), '/account/tmp'));
    }
}
