<?php

namespace App\Support;

class AuditLabels
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'membership.request.created' => 'Demande d\'adhésion créée',
        'approval.decision.recorded' => 'Décision d\'approbation',
        'moderation.report.created' => 'Signalement créé',
        'moderation.report.reviewed' => 'Signalement traité',
        'event.created' => 'Événement créé (API)',
        'event.registration.created' => 'Inscription à un événement',
        'admin.entity.created' => 'Entité créée',
        'admin.entity.updated' => 'Entité modifiée',
        'admin.entity.deleted' => 'Entité supprimée',
        'admin.department.created' => 'Département créé',
        'admin.department.updated' => 'Département modifié',
        'admin.department.deleted' => 'Département supprimé',
        'admin.institution.created' => 'Institution créée',
        'admin.institution.updated' => 'Institution modifiée',
        'admin.institution.deleted' => 'Institution supprimée',
        'admin.club.created' => 'Club créé',
        'admin.club.updated' => 'Club modifié',
        'admin.club.deleted' => 'Club supprimé',
        'admin.event.created' => 'Événement créé',
        'admin.event.updated' => 'Événement modifié',
        'admin.event.deleted' => 'Événement supprimé',
        'admin.event.registration_updated' => 'Inscription modifiée',
        'admin.role.assigned' => 'Rôle attribué',
        'admin.role.revoked' => 'Rôle retiré',
        'admin.membership.status_changed' => 'Statut d\'adhésion modifié',
        'admin.post.deleted' => 'Publication supprimée',
        'admin.post.comments_toggled' => 'Commentaires activés/désactivés',
        'admin.moderation.reviewed' => 'Signalement traité (admin)',
        'admin.roadmap.updated' => 'Feuille de route mise à jour',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::LABELS;
    }
}
