<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedSidebarsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_feed_exposes_active_partners_with_their_logo_and_the_mission_link(): void
    {
        Partner::query()->create(['name' => 'Partenaire Visible', 'logo_path' => 'partners/logos/a.png', 'website_url' => 'https://exemple.tn', 'is_active' => true]);
        Partner::query()->create(['name' => 'Partenaire Sans Lien', 'logo_path' => 'partners/logos/c.png', 'website_url' => 'javascript:alert(1)', 'is_active' => true]);
        Partner::query()->create(['name' => 'Partenaire Masque', 'logo_path' => 'partners/logos/b.png', 'is_active' => false]);

        $payload = $this->actingAs(User::factory()->create())->get('/feed')->assertOk()->viewData('partners');

        $this->assertSame(['Partenaire Visible', 'Partenaire Sans Lien'], $payload->pluck('name')->all());
        $this->assertStringContainsString('partners/logos/a.png', $payload[0]['logo_url']);
        $this->assertSame('https://exemple.tn', $payload[0]['url']);
        $this->assertNull($payload[1]['url']);

        $this->get('/feed')->assertOk()->assertSee('/a-propos#mission', false);
    }

    public function test_the_mission_anchor_exists_on_the_about_page(): void
    {
        $this->get('/fr/a-propos')->assertOk()->assertSee('id="mission"', false);
    }
}
