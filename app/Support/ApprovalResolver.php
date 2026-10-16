<?php

namespace App\Support;

use App\Models\RoleAssignment;

class ApprovalResolver
{
    public static function resolveMembershipReviewer(int $entityId, int $requesterId): ?int
    {
        $priorityRoleSlugs = [
            'entity-administrator',
            'national-bureau-administrator',
            'foundation-president',
        ];

        foreach ($priorityRoleSlugs as $slug) {
            $assignment = RoleAssignment::query()
                ->where('status', 'active')
                ->where('user_id', '!=', $requesterId)
                ->where(function ($builder) use ($entityId): void {
                    $builder->whereNull('entity_id')->orWhere('entity_id', $entityId);
                })
                ->whereHas('role', fn ($builder) => $builder->where('slug', $slug))
                ->orderBy('id')
                ->first();

            if ($assignment) {
                return $assignment->user_id;
            }
        }

        return null;
    }
}
