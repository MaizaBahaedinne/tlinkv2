<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vitrines publiques de Tunivisions Stores et Academy (sans sous-domaines : /boutique/presentation et /academy/presentation). */
class ShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_showcase_is_public_and_announces_the_opening(): void
    {
        $this->get('/boutique/presentation')
            ->assertOk()
            ->assertSee('Tunivisions Stores')
            ->assertSee('Ouverture prochainement');
    }

    public function test_store_showcase_links_to_the_shop_once_open(): void
    {
        Setting::query()->create(['group' => 'store', 'key' => 'config', 'value' => json_encode(['enabled' => true])]);

        $this->get('/boutique/presentation')
            ->assertOk()
            ->assertDontSee('Ouverture prochainement')
            ->assertSee(route('store.index'), false);
    }

    public function test_academy_showcase_is_public(): void
    {
        $this->get('/academy/presentation')
            ->assertOk()
            ->assertSee('Tunivisions Academy')
            ->assertSee(route('academy.index'), false);
    }
}
