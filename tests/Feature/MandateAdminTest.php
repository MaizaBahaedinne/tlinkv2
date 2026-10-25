<?php

namespace Tests\Feature;

use App\Models\Mandate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MandateAdminTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_only_entity_managers_can_access_mandates_screen(): void
    {
        $this->get('/admin/mandats')->assertRedirect('/connexion');

        $member = User::factory()->create();
        $this->actingAs($member)->get('/admin/mandats')->assertForbidden();

        $moderator = User::factory()->create();
        $this->grantAccess($moderator, 'moderator', 'university');
        $this->actingAs($moderator)->get('/admin/mandats')->assertForbidden();

        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $this->actingAs($admin)->get('/admin/mandats')->assertOk();
    }

    public function test_first_open_bootstraps_mandates_from_2016_until_current_year(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->get('/admin/mandats')->assertOk();

        $first = Mandate::query()->orderBy('start_year')->first();
        $last = Mandate::query()->orderByDesc('start_year')->first();

        $this->assertSame(2016, $first?->start_year);
        $this->assertSame(2017, $first?->end_year);
        $this->assertSame((int) now()->year, $last?->start_year);
        $this->assertSame((int) now()->year + 1, $last?->end_year);
        $this->assertTrue((bool) $last?->is_current);
    }

    public function test_admin_can_create_update_and_delete_a_mandate(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)
            ->post('/admin/mandats', [
                'start_year' => 2027,
                'end_year' => 2028,
                'bureau_name' => 'Bureau national 2027-2028',
                'notes' => 'Premier bureau élu du mandat.',
                'is_current' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $mandate = Mandate::query()->where('label', '2027-2028')->firstOrFail();
        $this->assertTrue($mandate->is_current);

        $this->actingAs($admin)
            ->put("/admin/mandats/{$mandate->id}", [
                'start_year' => 2027,
                'end_year' => 2028,
                'bureau_name' => 'Bureau mis à jour',
                'notes' => 'Mise à jour.',
                'is_current' => '0',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame('Bureau mis à jour', $mandate->fresh()->bureau_name);
        $this->assertFalse($mandate->fresh()->is_current);

        $this->actingAs($admin)
            ->delete("/admin/mandats/{$mandate->id}")
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('mandates', ['id' => $mandate->id]);
    }

    public function test_only_one_mandate_can_be_current(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        Mandate::query()->create([
            'label' => '2024-2025',
            'start_year' => 2024,
            'end_year' => 2025,
            'bureau_name' => 'Bureau A',
            'is_current' => true,
        ]);

        $second = Mandate::query()->create([
            'label' => '2025-2026',
            'start_year' => 2025,
            'end_year' => 2026,
            'bureau_name' => 'Bureau B',
            'is_current' => false,
        ]);

        $this->actingAs($admin)
            ->put("/admin/mandats/{$second->id}", [
                'start_year' => 2025,
                'end_year' => 2026,
                'bureau_name' => 'Bureau B',
                'notes' => '',
                'is_current' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Mandate::query()->where('is_current', true)->count());
        $this->assertTrue($second->fresh()->is_current);
        $this->assertFalse(Mandate::query()->where('label', '2024-2025')->firstOrFail()->is_current);
    }
}
