<?php

namespace App\Support;

use App\Models\User;

/**
 * Éléments du profil à renseigner pour finir l'intégration : photo + CV (formation, expérience, compétences).
 */
final class ProfileCompleteness
{
    /**
     * @return list<string>
     */
    public static function missing(User $user): array
    {
        $missing = [];

        if (blank($user->profile?->photo_path)) {
            $missing[] = 'une photo de profil';
        }

        return [...$missing, ...MembershipRequests::missingCv($user)];
    }
}
