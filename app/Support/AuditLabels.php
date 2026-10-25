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
        'admin.user.updated' => 'Utilisateur modifié',
        'admin.event.created' => 'Événement créé',
        'admin.event.updated' => 'Événement modifié',
        'admin.event.deleted' => 'Événement supprimé',
        'admin.event.registration_updated' => 'Inscription modifiée',
        'admin.role.assigned' => 'Rôle attribué',
        'admin.role.revoked' => 'Rôle retiré',
        'admin.membership.status_changed' => 'Statut d\'adhésion modifié',
        'admin.post.deleted' => 'Publication supprimée',
        'admin.post.comments_toggled' => 'Commentaires activés/désactivés',
        'admin.post.site_news_toggled' => 'Publication autorisée/refusée pour le site vitrine',
        'admin.moderation.reviewed' => 'Signalement traité (admin)',
        'admin.roadmap.updated' => 'Feuille de route mise à jour',
        'admin.site.updated' => 'Site public mis à jour',
        'admin.mandate.created' => 'Mandat ajouté',
        'admin.mandate.updated' => 'Mandat modifié',
        'admin.mandate.deleted' => 'Mandat supprimé',
        'admin.scopes.updated' => 'Hiérarchie des périmètres modifiée',
        'admin.contact_message.updated' => 'Message de contact mis à jour',
        'admin.contact_message.deleted' => 'Message de contact supprimé',
        'orgchart.node.created' => 'Organigramme : bloc créé',
        'orgchart.node.updated' => 'Organigramme : bloc modifié',
        'orgchart.node.deleted' => 'Organigramme : bloc supprimé',
        'orgchart.node.reordered' => 'Organigramme : ordre modifié',
        'orgchart.layout.saved' => 'Organigramme : positions enregistrées',
        'orgchart.club.created' => 'Organigramme : club créé',
        'orgchart.club.template_applied' => 'Organigramme : équipe type appliquée',
        'admin.info_session.updated' => 'Info session et quiz modifiés',
        'infosession.passed' => 'Info session : compte validé par le quiz',
        'orgchart.club_roles.updated' => 'Organigramme : droits des rôles de club modifiés',
        'orgchart.club.status_bulk' => 'Organigramme : statut de plusieurs clubs modifié',
        'orgchart.club.aligned' => 'Organigramme : clubs alignés sur l’équipe type',
        'orgchart.access.bulk_updated' => 'Organigramme : niveau d’accès de plusieurs postes modifié',
        'orgchart.assignments.updated' => 'Organigramme : affectations modifiées',
        'orgchart.person.created' => 'Organigramme : personne ajoutée',
        'orgchart.person.updated' => 'Organigramme : personne modifiée',
        'orgchart.person.deleted' => 'Organigramme : personne supprimée',
        'orgchart.imported' => 'Organigramme : import JSON',
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
