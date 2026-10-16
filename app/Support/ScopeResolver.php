<?php

namespace App\Support;

use App\Models\RoleAssignment;
use App\Models\User;

class ScopeResolver
{
    /**
     * @return array<int>
     */
    public static function managedEntityIds(User $user, string $permissionSlug): array
    {
        $assignments = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('role.permissions', fn ($builder) => $builder->where('slug', $permissionSlug))
            ->get(['entity_id']);

        $hasGlobal = $assignments->contains(fn ($assignment) => $assignment->entity_id === null);
        if ($hasGlobal) {
            return [];
        }

        return $assignments
            ->pluck('entity_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
