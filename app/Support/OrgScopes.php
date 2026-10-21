<?php

namespace App\Support;

/**
 * Perimetres de la fondation : ils remplacent l'ancienne table `entities`.
 * Un bloc de l'organigramme porte un perimetre (ou en herite) ; les adhesions, publications et
 * evenements memorisent le perimetre auquel ils appartiennent.
 */
final class OrgScopes
{
    public const ALL = [
        'foundation' => 'Fondation',
        'university' => 'University',
        'high-school' => 'High School',
        'alumni' => 'Alumni',
    ];

    /** Branche de clubs de l'organigramme par perimetre : perimetre => [cle systeme, categorie]. */
    public const CLUB_BRANCHES = [
        'university' => ['university-clubs', 'university_club'],
        'high-school' => ['highschool-clubs', 'highschool_club'],
    ];

    public static function valid(?string $scope): bool
    {
        return $scope !== null && isset(self::ALL[$scope]);
    }

    public static function label(?string $scope): ?string
    {
        return $scope === null ? null : (self::ALL[$scope] ?? $scope);
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * Type d'etablissement d'un club selon son perimetre (null : aucun etablissement).
     */
    public static function institutionType(?string $scope): ?string
    {
        return match ($scope) {
            'university' => 'university',
            'high-school' => 'high_school',
            default => null,
        };
    }
}
