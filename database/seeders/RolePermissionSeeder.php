<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\RolePermissionMatrix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = RolePermissionMatrix::roles();

        foreach ($roles as $name => $scope) {
            Role::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'scope_type' => $scope,
                    'is_active' => true,
                ]
            );
        }

        $permissions = RolePermissionMatrix::permissions();

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                ]
            );
        }

        $permissionIdsBySlug = Permission::query()
            ->pluck('id', 'slug')
            ->all();

        foreach (RolePermissionMatrix::roleToPermissions() as $roleSlug => $permissionSlugs) {
            $role = Role::query()->where('slug', $roleSlug)->first();
            if (! $role) {
                continue;
            }

            $permissionIds = [];
            foreach ($permissionSlugs as $permissionSlug) {
                if (isset($permissionIdsBySlug[$permissionSlug])) {
                    $permissionIds[] = $permissionIdsBySlug[$permissionSlug];
                }
            }

            $role->permissions()->sync($permissionIds);
        }
    }
}
