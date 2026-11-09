<?php

namespace Tests\Feature;

use App\Models\Advert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AdvertsTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_the_feed_has_no_advert_when_none_is_active(): void
    {
        Advert::query()->create(['title' => 'Masquée', 'image_path' => 'adverts/a.gif', 'is_active' => false]);

        $this->actingAs(User::factory()->create())->get('/feed')->assertOk();
        $this->assertNull($this->get('/feed')->viewData('advert'));
    }

    public function test_the_feed_exposes_an_active_advert(): void
    {
        Advert::query()->create(['title' => 'Offre', 'image_path' => 'adverts/a.gif', 'link_url' => 'https://exemple.tn', 'is_active' => true]);

        $advert = $this->actingAs(User::factory()->create())->get('/feed')->assertOk()->viewData('advert');

        $this->assertSame('Offre', $advert['title']);
        $this->assertStringContainsString('adverts/a.gif', $advert['image_url']);
        $this->assertSame('https://exemple.tn', $advert['url']);
    }

    public function test_dangerous_link_scheme_is_never_linked(): void
    {
        $advert = new Advert(['title' => 'X', 'image_path' => 'adverts/x.gif', 'link_url' => 'javascript:alert(1)']);

        $this->assertNull($advert->safeUrl());
    }

    public function test_the_static_advert_is_gone_from_the_feed_screen(): void
    {
        $source = file_get_contents(resource_path('js/ui/App.vue'));

        $this->assertStringNotContainsString('Formation Leadership', $source);

        $aside = strpos($source, '<aside class="hidden lg:col-span-3');
        $this->assertLessThan(strpos($source, '<ActivityCalendar', $aside), strpos($source, '<PartnersCarousel', $aside));
    }

    public function test_global_admin_adds_edits_and_deletes_an_advert(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $this->actingAs($admin);

        $this->post('/admin/publicites', [
            'title' => 'Bannière',
            'link_url' => 'https://exemple.tn',
            'image' => UploadedFile::fake()->image('b.gif', 600, 200),
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $advert = Advert::query()->firstOrFail();
        Storage::disk('public')->assertExists($advert->image_path);
        $old = $advert->image_path;

        $this->put('/admin/publicites/'.$advert->id, [
            'title' => 'Renommée',
            'sort_order' => 2,
            'image' => UploadedFile::fake()->image('c.png', 600, 200),
        ])->assertSessionHasNoErrors();

        $advert->refresh();
        $this->assertSame('Renommée', $advert->title);
        $this->assertFalse($advert->is_active);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($advert->image_path);

        $this->delete('/admin/publicites/'.$advert->id)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('adverts', 0);
    }

    public function test_an_oversized_gif_gets_a_clear_message(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $this->actingAs($admin)->from('/admin/publicites')->post('/admin/publicites', [
            'title' => 'Lourde',
            'image' => UploadedFile::fake()->image('lourde.gif', 600, 200)->size(6000),
        ])->assertSessionHasErrors(['image' => 'Le fichier est trop lourd (5 Mo max). Réduisez le poids du GIF.']);
    }

    public function test_a_regular_member_cannot_manage_adverts(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/publicites')->assertForbidden();
    }
}
