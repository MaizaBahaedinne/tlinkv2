<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_user_cannot_access_admin_overview(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/overview');

        $response->assertForbidden();
    }

    public function test_admin_user_can_access_admin_overview(): void
    {
        $user = User::factory()->create();

        $permission = Permission::query()->firstOrCreate([
            'slug' => 'entity.manage',
        ], [
            'name' => 'Manage Entities',
        ]);

        $role = Role::query()->firstOrCreate([
            'slug' => 'entity-administrator',
        ], [
            'name' => 'Entity Administrator',
            'scope_type' => 'entity',
        ]);

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/overview');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'is_global',
                'managed_entity_ids',
                'stats' => ['users', 'posts', 'memberships_pending', 'approvals_pending'],
            ],
        ]);
    }
}
