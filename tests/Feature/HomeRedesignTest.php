<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class HomeRedesignTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_the_home_page_has_the_six_sections_of_the_new_design(): void
    {
        $this->get('/fr')->assertOk()
            ->assertSee('hero-immersive', false)
            ->assertSee('Autonomisation')
            ->assertSee('Engagement')
            ->assertSee('Innovation')
            ->assertSee('Notre présence en Tunisie')
            ->assertSee('id="clubs-map"', false)
            ->assertSee('Nous rejoindre en 3 étapes')
            ->assertSee('Actualités et événements')
            ->assertSee('cta-band', false);
    }

    public function test_the_missions_use_the_foundation_photographs_by_default(): void
    {
        $this->get('/fr')->assertOk()
            ->assertSee('hero/hero-mark-b.webp', false)
            ->assertSee('hero/hero-main.webp', false)
            ->assertSee('hero/hero-mark-a.webp', false);
    }

    public function test_the_home_page_is_available_in_every_language(): void
    {
        $this->get('/en')->assertOk()->assertSee('Empowerment')->assertSee('Our presence in Tunisia');
        $this->get('/ar')->assertOk()->assertSee('التمكين')->assertSee('حضورنا في تونس');
    }

    public function test_the_footer_groups_the_institutional_navigation_and_the_contact(): void
    {
        $this->get('/fr')->assertOk()
            ->assertSee('La Fondation')
            ->assertSee('Communauté')
            ->assertSee('Propulsé par Persomy.com');
    }

    public function test_an_admin_replaces_a_mission_photo_and_removes_it_again(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $this->actingAs($admin);

        $this->put('/admin/site-public', ['mission_1_photo_file' => UploadedFile::fake()->image('m1.jpg', 800, 1000)])->assertSessionHasNoErrors();

        $path = (new SiteSettings())->stored('mission_1_photo');
        $this->assertStringStartsWith('site/missions/', $path);
        Storage::disk('public')->assertExists($path);
        $this->get('/fr')->assertSee($path, false);

        $this->put('/admin/site-public', ['mission_1_photo_remove' => '1'])->assertSessionHasNoErrors();

        $this->assertSame('', (new SiteSettings())->stored('mission_1_photo'));
        Storage::disk('public')->assertMissing($path);
    }
}
