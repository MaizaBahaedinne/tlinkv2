<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Les applications s'affichent pleine largeur et sans zoom, dans les dimensions de l'appareil. */
class AppShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_pages_are_full_width_and_cannot_be_zoomed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/feed')->assertOk()
            ->assertSee('<body class="app-full">', false)
            ->assertSee('maximum-scale=1', false)
            ->assertSee('user-scalable=no', false);
    }

    public function test_the_stylesheet_removes_margins_radius_and_horizontal_overflow(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.app-full .app-container', $css);
        $this->assertStringContainsString('overflow-x: clip', $css);
        $this->assertStringContainsString('border-radius: 0', $css);
    }
}
