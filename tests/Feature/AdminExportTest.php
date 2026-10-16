<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_export_users_csv(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/v1/admin/exports/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_export_users_csv(): void
    {
        $admin = User::factory()->create();

        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'entity.manage'],
            ['name' => 'Manage Entities']
        );

        $role = Role::query()->firstOrCreate(
            ['slug' => 'entity-administrator'],
            ['name' => 'Entity Administrator', 'scope_type' => 'entity']
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        RoleAssignment::query()->create([
            'user_id' => $admin->id,
            'role_id' => $role->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->get('/api/v1/admin/exports/users');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
