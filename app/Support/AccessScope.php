<?php

namespace App\Support;

use App\Models\RoleAssignment;
use App\Models\User;

class AccessScope
{
    public static function can(User $user, string $permissionSlug, ?int $entityId = null): bool
    {
        $query = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('role', function ($builder): void {
                $builder->where('is_active', true);
            })
            ->whereHas('role.permissions', fn ($builder) => $builder->where('slug', $permissionSlug));

        if ($entityId !== null) {
            $query->where(function ($builder) use ($entityId): void {
                $builder->whereNull('entity_id')->orWhere('entity_id', $entityId);
            });
        }

        return $query->exists();
    }
}
