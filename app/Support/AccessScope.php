<?php

namespace App\Support;

use App\Models\User;

class AccessScope
{
    public static function can(User $user, string $permissionSlug, ?string $scope = null): bool
    {
        $scopes = OrgAccess::grants($user)[$permissionSlug] ?? null;

        if ($scopes === null) {
            return false;
        }

        if ($scope === null || in_array(null, $scopes, true)) {
            return true;
        }

        return in_array($scope, $scopes, true);
    }
}
