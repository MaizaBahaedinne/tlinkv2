<?php

namespace App\Support;

/**
 * Niveaux d'acces portes par les postes de l'organigramme : ils remplacent les anciens roles.
 * Une personne affectee a un poste recoit les permissions du niveau de ce poste,
 * limitees a l'entite du poste (explicite ou heritee de l'organigramme) sauf pour l'administrateur global.
 */
final class AccessLevels
{
    public const NONE = 'none';

    public const GLOBAL_ADMIN = 'global_admin';

    /** Droits choisis un par un sur le poste (colonne org_nodes.permissions). */
    public const CUSTOM = 'custom';

    /** Marqueur stocke avec les droits d'un poste personnalise : il agit sur toute la plateforme, sans limite de perimetre. */
    public const SCOPE_ALL = 'scope.all';

    public const LEVELS = [
        'none' => 'Aucun droit d’administration',
        'global_admin' => 'Administrateur global',
        'entity_admin' => 'Administrateur d’entité',
        'manager' => 'Responsable (adhésions, validations, modération, événements)',
        'club_president' => 'Président de club (adhésions, validations, événements)',
        'moderator' => 'Modérateur (modération des publications)',
        'custom' => 'Personnalisé (droits choisis pour ce poste)',
    ];

    /** Libelles courts et tons de badge pour les tableaux : niveau => [libelle, ton]. */
    public const BADGES = [
        'none' => ['Aucun droit', 'neutral'],
        'global_admin' => ['Admin global', 'danger'],
        'entity_admin' => ['Admin d’entité', 'warning'],
        'manager' => ['Responsable', 'info'],
        'club_president' => ['Président de club', 'info'],
        'moderator' => ['Modérateur', 'neutral'],
        'custom' => ['Personnalisé', 'info'],
    ];

    public const PERMISSIONS = [
        'entity.manage' => 'Piloter l’organisation (entités, clubs, organigramme)',
        'membership.manage' => 'Gérer les adhésions',
        'approval.review' => 'Valider ou rejeter les demandes',
        'post.moderate' => 'Modérer les publications',
        'post.publish_public' => 'Publier des contenus visibles publiquement',
        'event.manage' => 'Créer et administrer les événements',
    ];

    /** Libelles courts pour les en-tetes de tableau. */
    public const SHORT = [
        'entity.manage' => 'Organisation',
        'membership.manage' => 'Adhésions',
        'approval.review' => 'Validations',
        'post.moderate' => 'Modération',
        'post.publish_public' => 'Publication publique',
        'event.manage' => 'Événements',
    ];

    private const LEVEL_PERMISSIONS = [
        'global_admin' => ['entity.manage', 'membership.manage', 'approval.review', 'post.moderate', 'post.publish_public', 'event.manage'],
        'entity_admin' => ['entity.manage', 'membership.manage', 'approval.review', 'post.moderate', 'post.publish_public', 'event.manage'],
        'manager' => ['membership.manage', 'approval.review', 'post.moderate', 'post.publish_public', 'event.manage'],
        'club_president' => ['membership.manage', 'approval.review', 'post.publish_public', 'event.manage'],
        'moderator' => ['post.moderate'],
    ];

    /**
     * @return array<int, string>
     */
    public static function permissions(string $level): array
    {
        return self::LEVEL_PERMISSIONS[$level] ?? [];
    }

    public static function isGlobal(string $level): bool
    {
        return $level === self::GLOBAL_ADMIN;
    }

    /**
     * Droits effectifs d'un poste : ceux de son niveau, ou ceux coches sur le poste (niveau personnalise).
     *
     * @param array<int, string>|null $custom
     * @return array<int, string>
     */
    public static function forPosition(string $level, ?array $custom): array
    {
        if ($level !== self::CUSTOM) {
            return self::permissions($level);
        }

        return array_values(array_intersect(array_keys(self::PERMISSIONS), $custom ?? []));
    }

    /**
     * Le poste agit-il sur toute la plateforme (sinon : uniquement dans son perimetre) ?
     *
     * @param array<int, string>|null $custom
     */
    public static function reachesEverywhere(string $level, ?array $custom): bool
    {
        return self::isGlobal($level) || ($level === self::CUSTOM && in_array(self::SCOPE_ALL, $custom ?? [], true));
    }

    /**
     * Niveaux predefinis qui donnent une permission (pour filtrer les tableaux).
     *
     * @return array<int, string>
     */
    public static function levelsGranting(string $permission): array
    {
        return array_keys(array_filter(self::LEVEL_PERMISSIONS, fn (array $list): bool => in_array($permission, $list, true)));
    }

    /**
     * Niveau et droits a stocker pour des droits coches sur un poste.
     *
     * @param array<int, mixed> $checked
     * @return array{0: string, 1: array<int, string>|null}
     */
    public static function fromChecked(array $checked, bool $everywhere): array
    {
        $permissions = array_values(array_intersect(array_keys(self::PERMISSIONS), $checked));

        if ($permissions === []) {
            return [self::NONE, null];
        }

        return [self::CUSTOM, $everywhere ? [...$permissions, self::SCOPE_ALL] : $permissions];
    }
}
