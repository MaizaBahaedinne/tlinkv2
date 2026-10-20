<?php

namespace App\Support;

use App\Models\User;

class ScopeResolver
{
    /**
     * Entites que l'utilisateur gere pour une permission. [] = perimetre global (ou aucune permission :
     * les appelants verifient d'abord AccessScope::can()).
     *
     * @return array<int>
     */
    public static function managedEntityIds(User $user, string $permissionSlug): array
    {
        $scopes = OrgAccess::grants($user)[$permissionSlug] ?? [];

        if (in_array(null, $scopes, true)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $scopes)));
    }
}
