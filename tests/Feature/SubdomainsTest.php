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

    public function test_legacy_store_and_academy_paths_redirect_to_their_host(): void
    {
        $this->get('http://link.example.test/boutique/panier?x=1')->assertStatus(301)->assertRedirect(Domains::url('store', '/boutique/panier?x=1'));
        $this->get('http://example.test/academy')->assertStatus(301)->assertRedirect(Domains::url('academy', '/academy'));
        $this->get('http://link.example.test/certificats/TA-ABCDEFGHIJ')->assertStatus(301)->assertRedirect(Domains::url('academy', '/certificats/TA-ABCDEFGHIJ'));
        $this->get('http://link.example.test/n-importe-quoi')->assertNotFound();
    }

    public function test_app_pages_requested_on_other_hosts_redirect_to_the_app_host(): void
    {
        $this->get('http://store.example.test/feed')->assertStatus(301)->assertRedirect(Domains::url('app', '/feed'));
        $this->get('http://example.test/connexion')->assertStatus(301)->assertRedirect(Domains::url('app', '/connexion'));
        $this->post('http://store.example.test/posts', [])->assertStatus(308)->assertRedirect(Domains::url('app', '/posts'));
        $this->get('http://link.example.test/connexion')->assertOk();
    }

    public function test_shared_routes_stay_on_the_current_host(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('http://store.example.test/messagerie/non-lus')->assertOk()->assertJson(['unread' => 0]);
        $this->actingAs($user)->getJson('http://academy.example.test/messagerie/non-lus')->assertOk();
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

    public function test_showcases_link_to_the_app_host_for_login_and_to_each_other(): void
    {
        $this->get('http://store.example.test/')
            ->assertSee(Domains::url('app', '/connexion'), false)
            ->assertSee(route('site.about'), false)
            ->assertSee(route('academy.showcase'), false);

        $this->get('http://academy.example.test/')->assertSee(route('store.showcase'), false);
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
