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

    private function setTag(string $id): void
    {
        (new SiteSettings())->save(['google_tag_id' => $id]);
    }

    public function test_the_migrations_install_the_gtag_measurement_id(): void
    {
        $this->assertSame('G-WX9RVDBH4P', SiteSettings::googleTagId());

        $html = $this->get('/fr')->assertOk()->getContent();

        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-WX9RVDBH4P', $html);
        $this->assertStringContainsString("gtag('config', \"G-WX9RVDBH4P\")", $html);
        $this->assertSame(1, substr_count($html, 'googletagmanager.com'));
        $this->assertStringNotContainsString('gtm.js', $html);
        $this->assertLessThan(strpos($html, '<meta charset'), strpos($html, 'gtag/js?id='));
    }

    public function test_a_tag_manager_container_renders_the_gtm_snippet_and_noscript(): void
    {
        $this->setTag('GTM-P3PW7PKX');

        $html = $this->get('/fr')->assertOk()->getContent();

        $this->assertStringContainsString('www.googletagmanager.com/gtm.js?id=', $html);
        $this->assertStringContainsString('"GTM-P3PW7PKX"', $html);
        $this->assertStringContainsString('ns.html?id=GTM-P3PW7PKX', $html);
        $this->assertStringNotContainsString('gtag/js', $html);
        $this->assertGreaterThan(strpos($html, '<body'), strpos($html, 'ns.html?id='));
    }

    public function test_a_migrated_tag_manager_default_is_switched_but_a_custom_value_is_kept(): void
    {
        $migration = require database_path('migrations/2026_11_24_100000_use_gtag_measurement_id.php');

        $this->setTag('GTM-P3PW7PKX');
        $migration->up();
        $this->assertSame('G-WX9RVDBH4P', (new SiteSettings())->stored('google_tag_id'));

        $this->setTag('GTM-CUSTOM123');
        $migration->up();
        $this->assertSame('GTM-CUSTOM123', (new SiteSettings())->stored('google_tag_id'));
    }


    public function test_tag_is_in_the_head_of_the_app_pages(): void
    {
        $this->setTag('G-ABCD1234');
        $user = User::factory()->create(['onboarding_completed_at' => now()]);

        $html = $this->actingAs($user)->get('/profil')->getContent();

        $this->assertStringContainsString('gtag/js?id=G-ABCD1234', $html);
        $this->assertLessThan(strpos($html, '<meta charset'), strpos($html, 'gtag/js?id=G-ABCD1234'));
    }

    public function test_no_tag_when_empty_or_invalid(): void
    {
        $this->setTag('');
        $this->get('/fr')->assertOk()->assertDontSee('googletagmanager', false);

        $this->setTag('"><script>alert(1)</script>');
        $this->get('/fr')->assertOk()->assertDontSee('googletagmanager', false)->assertDontSee('alert(1)', false);
    }

    public function test_admin_sets_the_tag_and_invalid_ids_are_refused(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $this->actingAs($admin)->put('/admin/site-public', ['google_tag_id' => 'g-new12345'])->assertSessionHasNoErrors();
        $this->assertSame('G-NEW12345', (new SiteSettings())->stored('google_tag_id'));

        $this->put('/admin/site-public', ['google_tag_id' => 'nimporte quoi'])->assertSessionHasErrors('google_tag_id');
        $this->assertSame('G-NEW12345', (new SiteSettings())->stored('google_tag_id'));

        $this->put('/admin/site-public', ['google_tag_id' => ''])->assertSessionHasNoErrors();
        $this->get('/fr')->assertDontSee('googletagmanager', false);
    }
}
