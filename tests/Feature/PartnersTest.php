<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class PartnersTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function globalAdmin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    public function test_home_hides_the_partners_section_when_there_is_none(): void
    {
        $this->get('/fr')->assertOk()->assertDontSee('Nos partenaires');
    }

    public function test_home_shows_only_active_partners(): void
    {
        Partner::query()->create(['name' => 'Partenaire Visible', 'logo_path' => 'partners/logos/a.png', 'website_url' => 'https://exemple.tn', 'is_active' => true]);
        Partner::query()->create(['name' => 'Partenaire Masque', 'logo_path' => 'partners/logos/b.png', 'is_active' => false]);

        $this->get('/fr')->assertOk()->assertSee('Nos partenaires')->assertSee('Partenaire Visible')->assertDontSee('Partenaire Masque');
    }

    public function test_dangerous_website_scheme_is_never_linked(): void
    {
        $partner = new Partner(['name' => 'X', 'logo_path' => 'partners/logos/x.png', 'website_url' => 'javascript:alert(1)']);

        $this->assertNull($partner->safeUrl());
    }

    public function test_global_admin_adds_edits_and_deletes_a_partner(): void
    {
        Storage::fake('public');
        $this->actingAs($this->globalAdmin());

        $this->post('/admin/partenaires', [
            'name' => 'Ministère',
            'website_url' => 'https://exemple.tn',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 100),
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $partner = Partner::query()->firstOrFail();
        Storage::disk('public')->assertExists($partner->logo_path);
        $oldLogo = $partner->logo_path;

        $this->put('/admin/partenaires/'.$partner->id, [
            'name' => 'Ministère renommé',
            'sort_order' => 5,
            'logo' => UploadedFile::fake()->image('nouveau.png', 200, 100),
        ])->assertSessionHasNoErrors();

        $partner->refresh();
        $this->assertSame('Ministère renommé', $partner->name);
        $this->assertFalse($partner->is_active);
        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertExists($partner->logo_path);

        $this->delete('/admin/partenaires/'.$partner->id)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('partners', 0);
        Storage::disk('public')->assertMissing($partner->logo_path);
    }

    public function test_logo_is_required_and_must_be_an_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->globalAdmin());

        $this->post('/admin/partenaires', ['name' => 'Sans logo'])->assertSessionHasErrors('logo');
        $this->post('/admin/partenaires', ['name' => 'Faux', 'logo' => UploadedFile::fake()->create('script.php', 5, 'text/php')])->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('partners', 0);
    }

    public function test_non_admin_cannot_manage_partners(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/partenaires')->assertForbidden();
        $this->post('/admin/partenaires', ['name' => 'X'])->assertForbidden();
    }
}
