<?php

namespace App\Support;

use App\Models\User;

class AccessScope
{
    public static function can(User $user, string $permissionSlug, ?int $entityId = null): bool
    {
        $scopes = OrgAccess::grants($user)[$permissionSlug] ?? null;

        if ($scopes === null) {
            return false;
        }

        if ($entityId === null || in_array(null, $scopes, true)) {
            return true;
        }

        return in_array($entityId, $scopes, true);
    }
}
