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

    public const LEVELS = [
        'none' => 'Aucun droit d’administration',
        'global_admin' => 'Administrateur global',
        'entity_admin' => 'Administrateur d’entité',
        'manager' => 'Responsable (adhésions, validations, modération, événements)',
        'club_president' => 'Président de club (adhésions, validations, événements)',
        'moderator' => 'Modérateur (modération des publications)',
    ];

    /** Libelles courts et tons de badge pour les tableaux : niveau => [libelle, ton]. */
    public const BADGES = [
        'none' => ['Aucun droit', 'neutral'],
        'global_admin' => ['Admin global', 'danger'],
        'entity_admin' => ['Admin d’entité', 'warning'],
        'manager' => ['Responsable', 'info'],
        'club_president' => ['Président de club', 'info'],
        'moderator' => ['Modérateur', 'neutral'],
    ];

    public const PERMISSIONS = [
        'entity.manage' => 'Piloter l’organisation (entités, clubs, organigramme)',
        'membership.manage' => 'Gérer les adhésions',
        'approval.review' => 'Valider ou rejeter les demandes',
        'post.moderate' => 'Modérer les publications',
        'event.manage' => 'Créer et administrer les événements',
    ];

    private const LEVEL_PERMISSIONS = [
        'global_admin' => ['entity.manage', 'membership.manage', 'approval.review', 'post.moderate', 'event.manage'],
        'entity_admin' => ['entity.manage', 'membership.manage', 'approval.review', 'post.moderate', 'event.manage'],
        'manager' => ['membership.manage', 'approval.review', 'post.moderate', 'event.manage'],
        'club_president' => ['membership.manage', 'approval.review', 'event.manage'],
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
}
