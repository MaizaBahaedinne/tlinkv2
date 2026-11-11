<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class RealignMatrixMigrationTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_held_permission_without_its_module_in_the_matrix_is_realigned(): void
    {
        $manager = User::factory()->create();
        $node = $this->grantAccess($manager, 'manager', 'university');
        $node->update(['module_permissions' => ['members.memberships' => ['read']]]);

        $this->actingAs($manager)->get('/admin/approbations')->assertForbidden();

        (require base_path('database/migrations/2026_12_14_110000_realign_position_matrices_with_permissions.php'))->up();

        $matrix = (array) $node->fresh()->module_permissions;
        $this->assertSame(['read', 'update'], $matrix['governance.approvals']);
        $this->assertSame(['read'], $matrix['members.memberships']);
        $this->actingAs($manager)->get('/admin/approbations')->assertOk();
    }
}
