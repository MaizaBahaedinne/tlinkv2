<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Domains;
use App\Support\LaunchGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Découpage en sous-domaines : les hôtes sont lus au démarrage (les routes en dépendent), on les fixe donc avant de créer l'application.
 */
class SubdomainsTest extends TestCase
{
    use RefreshDatabase;

    private const HOSTS = [
        'DOMAIN_SITE' => 'example.test',
        'DOMAIN_APP' => 'link.example.test',
        'DOMAIN_STORE' => 'store.example.test',
        'DOMAIN_ACADEMY' => 'academy.example.test',
        'DOMAIN_MESSENGER' => 'messenger.example.test',
    ];

    protected function setUp(): void
    {
        foreach (self::HOSTS as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_keys(self::HOSTS) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function test_urls_are_generated_on_the_host_of_each_section(): void
    {
        $this->assertSame(Domains::url('store', '/boutique'), route('store.index'));
        $this->assertSame(Domains::url('academy', '/academy'), route('academy.index'));
        $this->assertSame(Domains::url('site', '/fr'), route('site.home', ['locale' => 'fr']));
    }

    public function test_site_is_served_on_its_host_only(): void
    {
        $this->get('http://example.test/fr')->assertOk();
        $this->get('http://link.example.test/fr')->assertStatus(301)->assertRedirect(Domains::url('site', '/fr'));
        $this->get('http://link.example.test/de')->assertNotFound();
    }

    public function test_legacy_paths_redirect_from_the_site_host_but_are_not_found_from_another_application(): void
    {
        $this->get('http://example.test/boutique/panier?x=1')->assertStatus(301)->assertRedirect(Domains::url('store', '/boutique/panier?x=1'));
        $this->get('http://example.test/academy')->assertStatus(301)->assertRedirect(Domains::url('academy', '/academy'));
        $this->get('http://example.test/certificats/TA-ABCDEFGHIJ')->assertStatus(301)->assertRedirect(Domains::url('academy', '/certificats/TA-ABCDEFGHIJ'));
        $this->get('http://link.example.test/n-importe-quoi')->assertNotFound();

        // Chaque application est cloisonnée : l'ancien chemin d'une autre application n'y existe plus.
        $this->get('http://link.example.test/boutique/panier')->assertNotFound();
        $this->get('http://link.example.test/certificats/TA-ABCDEFGHIJ')->assertNotFound();
        $this->get('http://store.example.test/academy')->assertNotFound();
        $this->get('http://messenger.example.test/boutique')->assertNotFound();
    }

    public function test_sign_in_pages_requested_on_other_hosts_redirect_to_the_app_host(): void
    {
        $this->get('http://example.test/connexion')->assertStatus(301)->assertRedirect(Domains::url('app', '/connexion'));
        $this->get('http://store.example.test/connexion')->assertStatus(301)->assertRedirect(Domains::url('app', '/connexion'));
        $this->post('http://academy.example.test/connexion', [])->assertStatus(308)->assertRedirect(Domains::url('app', '/connexion'));
        $this->get('http://link.example.test/connexion')->assertOk();
    }

    public function test_each_application_cannot_reach_the_pages_of_the_others(): void
    {
        $user = User::factory()->create();

        foreach (['store', 'academy', 'messenger'] as $host) {
            $this->actingAs($user)->get("http://{$host}.example.test/feed")->assertNotFound();
            $this->actingAs($user)->get("http://{$host}.example.test/notifications")->assertNotFound();
            $this->actingAs($user)->get("http://{$host}.example.test/recherche")->assertNotFound();
            $this->actingAs($user)->post("http://{$host}.example.test/posts", [])->assertNotFound();
        }

        foreach (['link', 'store', 'academy'] as $host) {
            $this->actingAs($user)->get("http://{$host}.example.test/messagerie")->assertNotFound();
            $this->actingAs($user)->getJson("http://{$host}.example.test/messagerie/non-lus")->assertNotFound();
        }

        $this->actingAs($user)->get('http://link.example.test/feed')->assertOk();
        $this->actingAs($user)->get('http://messenger.example.test/messagerie')->assertOk();
    }

    public function test_technical_routes_stay_available_on_every_host(): void
    {
        foreach (['link', 'store', 'academy', 'messenger'] as $host) {
            $this->get("http://{$host}.example.test/manifest.webmanifest")->assertOk();
        }

        $user = User::factory()->create();

        $this->actingAs($user)->post('http://store.example.test/deconnexion')->assertRedirect();
    }

    public function test_logged_in_members_opening_the_sign_in_page_return_to_their_own_application(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('http://link.example.test/connexion')->assertRedirect(route('home'));
    }

    public function test_each_subdomain_publishes_its_own_manifest_with_its_logo(): void
    {
        $expected = [
            'link' => ['T.Link', '/feed', 'com.tunivisions.tlink', 'link'],
            'store' => ['Tunivisions Stores', '/boutique', 'com.tunivisions.stores', 'store'],
            'academy' => ['Tunivisions Academy', '/academy', 'com.tunivisions.academy', 'academy'],
            'messenger' => ['T.Link Messenger', '/messagerie', 'com.tunivisions.messenger', 'messenger'],
        ];

        foreach ($expected as $sub => [$name, $start, $package, $icons]) {
            $response = $this->get("http://{$sub}.example.test/manifest.webmanifest")->assertOk();
            $response->assertHeader('Content-Type', 'application/manifest+json');
            $response->assertJsonPath('name', $name)->assertJsonPath('start_url', $start)->assertJsonPath('related_applications.0.id', $package);

            foreach ($response->json('icons') as $icon) {
                $this->assertStringContainsString("/app-icons/{$icons}/icon-", $icon['src']);
                $this->assertFileExists(public_path(parse_url($icon['src'], PHP_URL_PATH)));
            }
        }
    }

    public function test_android_app_links_are_declared_per_host_with_valid_fingerprints_only(): void
    {
        $fingerprint = implode(':', array_fill(0, 32, 'AB'));

        $this->get('http://messenger.example.test/.well-known/assetlinks.json')->assertOk()->assertExactJson([]);

        config(['mobile.android_fingerprints' => [$fingerprint, 'pas-une-empreinte']]);

        $this->get('http://messenger.example.test/.well-known/assetlinks.json')->assertOk()->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => 'com.tunivisions.messenger', 'sha256_cert_fingerprints' => [$fingerprint]],
        ]]);
        $this->get('http://store.example.test/.well-known/assetlinks.json')->assertJsonPath('0.target.package_name', 'com.tunivisions.stores');
    }

    public function test_the_messenger_is_only_served_on_its_own_host_and_not_linked_from_the_others(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('http://messenger.example.test/messagerie')->assertOk();
        $this->actingAs($user)->get('http://link.example.test/messagerie')->assertNotFound();
        $this->actingAs($user)->get('http://store.example.test/messagerie/12')->assertNotFound();

        // T.Link propose le bouton Messenger, qui redirige vers l'application (autre hôte) ; ni tiroir de messages ni appel de comptage inter-hôtes.
        $this->actingAs($user)->get('http://link.example.test/feed')->assertOk()
            ->assertSee('id="nav-messages"', false)
            ->assertSee('href="'.Domains::url('messenger', '/messagerie').'"', false)
            ->assertDontSee('id="mg-dock"', false)
            ->assertDontSee('data-url-list', false);
    }

    public function test_the_install_app_button_is_in_the_headers_and_the_manifest_detects_installed_apps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('http://link.example.test/feed')->assertOk()->assertSee('data-install-app', false)->assertSee('Installer T.Link');
        $this->actingAs($user)->get('http://messenger.example.test/messagerie')->assertOk()->assertSee('data-install-app', false)->assertSee('Installer T.Link Messenger');

        $this->get('http://link.example.test/manifest.webmanifest')->assertOk()
            ->assertJsonPath('related_applications.0.platform', 'play')
            ->assertJsonPath('related_applications.1.platform', 'webapp')
            ->assertJsonPath('related_applications.1.url', 'http://link.example.test/manifest.webmanifest');
    }

    public function test_section_headers_only_contain_their_own_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('http://messenger.example.test/messagerie')->assertOk()
            ->assertSee('T.Link Messenger')
            ->assertDontSee(route('home'), false)
            ->assertDontSee(route('notifications.index'), false)
            ->assertDontSee(route('academy.index'), false)
            ->assertDontSee(route('store.index'), false)
            ->assertDontSee('id="nav-search"', false);

        $this->actingAs($user)->get('http://link.example.test/feed')->assertOk()
            ->assertDontSee(route('academy.index'), false)
            ->assertDontSee(route('store.index'), false);
    }

    public function test_messenger_host_opens_the_messenger_and_serves_its_routes(): void
    {
        $user = User::factory()->create();

        $this->get('http://messenger.example.test/')->assertRedirect('http://messenger.example.test/messagerie');
        $this->actingAs($user)->getJson('http://messenger.example.test/messagerie/non-lus')->assertOk()->assertJson(['unread' => 0]);
        $this->assertSame('messenger.example.test', Domains::host('messenger'));
    }

    public function test_app_root_leads_to_the_feed_and_store_academy_roots_are_public_showcases(): void
    {
        $this->get('http://link.example.test/')->assertRedirect('http://link.example.test/feed');

        $this->withHeader('Accept-Language', 'fr')->get('http://store.example.test/')->assertOk()->assertSee('Tunivisions Stores')->assertSee('Ouverture prochainement');
        $this->withHeader('Accept-Language', 'fr')->get('http://academy.example.test/')->assertOk()->assertSee('Tunivisions Academy');
    }

    public function test_showcases_link_to_the_app_host_for_login_only(): void
    {
        $this->get('http://store.example.test/')
            ->assertSee(Domains::url('app', '/connexion'), false)
            ->assertDontSee(route('site.about'), false)
            ->assertDontSee(route('academy.showcase'), false);

        $this->get('http://academy.example.test/')
            ->assertDontSee(route('store.showcase'), false)
            ->assertDontSee(route('site.about'), false);
    }

    public function test_push_devices_and_browsers_are_scoped_to_their_application(): void
    {
        $user = User::factory()->create();

        foreach (['app', 'messenger', 'store'] as $app) {
            \App\Models\PushDevice::query()->create(['user_id' => $user->id, 'token' => 'jeton-'.$app, 'platform' => 'android', 'app' => $app]);
            \App\Models\WebPushSubscription::query()->create([
                'user_id' => $user->id, 'app' => $app, 'endpoint_hash' => \App\Models\WebPushSubscription::hashEndpoint('https://fcm.googleapis.com/'.$app),
                'endpoint' => 'https://fcm.googleapis.com/'.$app, 'public_key' => 'k', 'auth_token' => 'a',
            ]);
        }

        $this->assertSame(['jeton-messenger'], \App\Models\PushDevice::query()->forSection('messenger')->pluck('token')->all());
        $this->assertSame(['jeton-app'], \App\Models\PushDevice::query()->forSection('app')->pluck('token')->all());
        $this->assertSame(['https://fcm.googleapis.com/store'], \App\Models\WebPushSubscription::query()->forSection('store')->pluck('endpoint')->all());
        // Pas de sous-domaine propre pour Academy : aucun filtrage.
        config(['domains.hosts.academy' => null]);
        $this->assertCount(3, \App\Models\PushDevice::query()->forSection('academy')->get());
    }

    public function test_each_application_uses_its_own_favicons(): void
    {
        $this->get('http://store.example.test/')->assertSee('app-icons/store/favicon.ico', false);
        $this->get('http://academy.example.test/')->assertSee('app-icons/academy/favicon-32x32.png', false);
        $this->get('http://link.example.test/connexion')->assertSee('app-icons/link/favicon.ico', false);
        $this->actingAs(User::factory()->create())->get('http://messenger.example.test/messagerie')->assertSee('app-icons/messenger/apple-touch-icon.png', false);

        $this->withHeader('Accept-Language', 'fr')->get('http://example.test/fr')->assertSee('favicon-white-32x32.png', false)->assertDontSee('app-icons/', false);
    }

    public function test_launch_countdown_only_covers_the_site_host_root(): void
    {
        $this->assertTrue(LaunchGate::isSitePath(Request::create('http://example.test/fr')));
        $this->assertFalse(LaunchGate::isSitePath(Request::create('http://store.example.test/')));
        $this->assertFalse(LaunchGate::isSitePath(Request::create('http://academy.example.test/')));
    }

    public function test_launch_countdown_does_not_cover_the_app_host_root(): void
    {
        $this->assertTrue(LaunchGate::isSitePath(Request::create('http://example.test/fr')));
        $this->assertFalse(LaunchGate::isSitePath(Request::create('http://link.example.test/fr')));
    }
}
