<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Foundation President' => 'global',
            'Former President' => 'global',
            'National Bureau Administrator' => 'global',
            'Entity Administrator' => 'entity',
            'Department Manager' => 'department',
            'Institution Manager' => 'institution',
            'Governorate Manager' => 'governorate',
            'Club President' => 'club',
            'Club Officer' => 'club',
            'Project Manager' => 'project',
            'Member' => 'club',
            'Public Visitor' => 'public',
        ];

        foreach ($roles as $name => $scope) {
            Role::query()->updateOrCreate(
                ['slug' => str($name)->slug()->toString()],
                [
                    'name' => $name,
                    'scope_type' => $scope,
                    'is_active' => true,
                ]
            );
        }

        $permissions = [
            ['name' => 'View Feed', 'slug' => 'feed.view'],
            ['name' => 'Create Post', 'slug' => 'post.create'],
            ['name' => 'Moderate Post', 'slug' => 'post.moderate'],
            ['name' => 'Manage Memberships', 'slug' => 'membership.manage'],
            ['name' => 'Approve Requests', 'slug' => 'approval.review'],
            ['name' => 'Manage Roles', 'slug' => 'role.manage'],
            ['name' => 'Manage Entities', 'slug' => 'entity.manage'],
            ['name' => 'Manage Events', 'slug' => 'event.manage'],
            ['name' => 'Register Events', 'slug' => 'event.register'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                ['name' => $permission['name']]
            );
        }

        $adminRole = Role::query()->where('slug', 'national-bureau-administrator')->first();
        $allPermissions = Permission::query()->pluck('id')->all();

        if ($adminRole) {
            $adminRole->permissions()->sync($allPermissions);
        }
    }
}
