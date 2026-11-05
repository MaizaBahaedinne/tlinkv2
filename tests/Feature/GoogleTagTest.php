<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class GoogleTagTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    /** @param array<string, string> $values */
    private function setTags(array $values): void
    {
        (new SiteSettings())->save($values + ['google_analytics_id' => '', 'google_tag_manager_id' => '']);
    }

    public function test_the_migrations_install_both_tools_independently(): void
    {
        $this->assertSame('G-WX9RVDBH4P', SiteSettings::googleAnalyticsId());
        $this->assertSame('GTM-P3PW7PKX', SiteSettings::googleTagManagerId());

        $html = $this->get('/fr')->assertOk()->getContent();

        // Google Analytics : snippet gtag.js
        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-WX9RVDBH4P', $html);
        $this->assertStringContainsString("gtag('config', \"G-WX9RVDBH4P\")", $html);

        // Google Tag Manager : snippet officiel + noscript après <body>
        $this->assertStringContainsString('www.googletagmanager.com/gtm.js?id=', $html);
        $this->assertStringContainsString('"GTM-P3PW7PKX"', $html);
        $this->assertStringContainsString('ns.html?id=GTM-P3PW7PKX', $html);
        $this->assertSame(1, substr_count($html, 'www.googletagmanager.com/gtm.js'));
        $this->assertSame(1, substr_count($html, 'www.googletagmanager.com/gtag/js'));

        $this->assertLessThan(strpos($html, '<meta charset'), strpos($html, 'gtm.start'));
        $this->assertLessThan(strpos($html, '<meta charset'), strpos($html, 'gtag/js?id='));
        $this->assertGreaterThan(strpos($html, '<body'), strpos($html, 'ns.html?id='));
    }

    public function test_analytics_alone_does_not_load_tag_manager(): void
    {
        $this->setTags(['google_analytics_id' => 'G-WX9RVDBH4P']);

        $this->get('/fr')->assertOk()
            ->assertSee('gtag/js?id=G-WX9RVDBH4P', false)
            ->assertDontSee('gtm.js', false)
            ->assertDontSee('ns.html', false);
    }

    public function test_tag_manager_alone_does_not_load_analytics(): void
    {
        $this->setTags(['google_tag_manager_id' => 'GTM-P3PW7PKX']);

        $this->get('/fr')->assertOk()
            ->assertSee('gtm.js?id=', false)
            ->assertSee('ns.html?id=GTM-P3PW7PKX', false)
            ->assertDontSee('gtag/js', false);
    }

    public function test_the_split_migration_distributes_the_legacy_single_field(): void
    {
        $migration = require database_path('migrations/2026_11_25_100000_split_google_analytics_and_tag_manager.php');

        (new SiteSettings())->save(['google_tag_id' => 'GTM-OLD12345']);
        $migration->up();
        $site = new SiteSettings();
        $this->assertSame('GTM-OLD12345', $site->stored('google_tag_manager_id'));
        $this->assertSame('G-WX9RVDBH4P', $site->stored('google_analytics_id'));
        $this->assertSame('', $site->stored('google_tag_id'));

        (new SiteSettings())->save(['google_tag_id' => 'G-OLD12345', 'google_tag_manager_id' => 'GTM-KEPT1234']);
        $migration->up();
        $site = new SiteSettings();
        $this->assertSame('G-OLD12345', $site->stored('google_analytics_id'));
        $this->assertSame('GTM-KEPT1234', $site->stored('google_tag_manager_id'));
    }

    public function test_tags_are_in_the_head_of_the_app_pages(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $html = $this->actingAs($user)->get('/profil')->getContent();

        $this->assertStringContainsString('gtag/js?id=G-WX9RVDBH4P', $html);
        $this->assertStringContainsString('gtm.js?id=', $html);
        $this->assertLessThan(strpos($html, '<meta charset'), strpos($html, 'gtag/js?id=G-WX9RVDBH4P'));
        $this->assertLessThan(strpos($html, '<meta charset'), strpos($html, 'gtm.start'));
    }

    public function test_nothing_is_rendered_when_empty_or_invalid(): void
    {
        $this->setTags([]);
        $this->get('/fr')->assertOk()->assertDontSee('googletagmanager', false);

        $this->setTags(['google_analytics_id' => '"><script>alert(1)</script>', 'google_tag_manager_id' => 'G-WX9RVDBH4P']);
        $this->get('/fr')->assertOk()->assertDontSee('googletagmanager', false)->assertDontSee('alert(1)', false);

        // Chaque champ n'accepte que son format : un GTM- n'est pas un identifiant Analytics, et inversement.
        $this->setTags(['google_analytics_id' => 'GTM-P3PW7PKX']);
        $this->get('/fr')->assertOk()->assertDontSee('googletagmanager', false);
    }

    public function test_admin_sets_each_tool_and_invalid_ids_are_refused(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $this->actingAs($admin)->put('/admin/site-public', ['google_analytics_id' => 'g-new12345', 'google_tag_manager_id' => 'gtm-new1234'])->assertSessionHasNoErrors();
        $site = new SiteSettings();
        $this->assertSame('G-NEW12345', $site->stored('google_analytics_id'));
        $this->assertSame('GTM-NEW1234', $site->stored('google_tag_manager_id'));

        $this->put('/admin/site-public', ['google_analytics_id' => 'GTM-P3PW7PKX'])->assertSessionHasErrors('google_analytics_id');
        $this->put('/admin/site-public', ['google_tag_manager_id' => 'G-WX9RVDBH4P'])->assertSessionHasErrors('google_tag_manager_id');
        $this->put('/admin/site-public', ['google_analytics_id' => 'nimporte quoi'])->assertSessionHasErrors('google_analytics_id');
        $this->assertSame('G-NEW12345', (new SiteSettings())->stored('google_analytics_id'));

        // Désactiver un outil sans toucher à l'autre.
        $this->put('/admin/site-public', ['google_analytics_id' => '', 'google_tag_manager_id' => 'GTM-NEW1234'])->assertSessionHasNoErrors();
        $this->get('/fr')->assertDontSee('gtag/js', false)->assertSee('gtm.js?id=', false);

        $this->get('/admin/site-public')->assertOk()->assertSee('Google Analytics')->assertSee('Google Tag Manager');
    }
}
