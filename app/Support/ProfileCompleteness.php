<?php

namespace App\Support;

use App\Models\User;

/**
 * Éléments du profil à renseigner pour finir l'intégration : photo, identité (genre, naissance, adresse, Facebook)
 * et CV (formation, compétences, langues). Les expériences restent facultatives.
 */
final class ProfileCompleteness
{
    /**
     * @return list<string>
     */
    public static function missing(User $user): array
    {
        $profile = $user->profile;
        $missing = [];

        if (blank($profile?->photo_path)) {
            $missing[] = 'une photo de profil';
        }

        if (blank($profile?->gender)) {
            $missing[] = 'votre genre';
        }

        if ($profile?->birth_date === null) {
            $missing[] = 'votre date de naissance';
        }

        if (blank($profile?->address)) {
            $missing[] = 'votre adresse';
        }

        if (blank($profile?->governorate_id)) {
            $missing[] = 'votre gouvernorat';
        }

        if (blank($profile?->delegation)) {
            $missing[] = 'votre délégation';
        }

        if (blank($profile?->facebook_url)) {
            $missing[] = 'votre page Facebook';
        }

        return [...$missing, ...MembershipRequests::missingCv($user)];
    }
}
