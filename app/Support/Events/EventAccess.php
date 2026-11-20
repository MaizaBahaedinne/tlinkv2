<?php

namespace App\Support\Events;

use App\Models\EventTeamMember;
use App\Models\FoundationEvent;
use App\Models\User;
use App\Support\AdminContext;
use App\Support\MembershipResponsibility;

/**
 * Droits de gestion d'un événement : responsables organisationnels (permission « event.manage » couvrant l'événement,
 * organisateur) ou membres de l'équipe d'organisation (OC) selon leur rôle.
 */
final class EventAccess
{
    /** Rôles OC => libellé. */
    public const ROLES = [
        'director' => 'Directeur / Chair',
        'coordinator' => 'Coordinateur',
        'program' => 'Responsable programme',
        'logistics' => 'Logistique',
        'communication' => 'Communication',
        'sponsoring' => 'Sponsoring & partenariats',
        'finance' => 'Finance & trésorerie',
        'registration' => 'Inscriptions & accueil',
        'volunteer' => 'Volontaire',
    ];

    /** Droits possibles, avec libellé. */
    public const ABILITIES = [
        'view' => "Consulter l'événement",
        'tickets' => 'Gérer la billetterie (releases)',
        'program' => 'Gérer le programme et les intervenants',
        'sponsors' => 'Gérer sponsors et partenaires',
        'registrations' => 'Gérer les inscriptions',
        'payments_record' => 'Enregistrer des paiements',
        'payments_validate' => 'Valider ou rejeter des paiements',
        'payments_settings' => 'Configurer les moyens de paiement',
        'team' => "Gérer l'équipe OC",
    ];

    public const ROLE_ABILITIES = [
        'director' => ['view', 'tickets', 'program', 'sponsors', 'registrations', 'payments_record', 'payments_validate', 'payments_settings', 'team'],
        'coordinator' => ['view', 'tickets', 'program', 'sponsors', 'registrations', 'team'],
        'program' => ['view', 'program'],
        'logistics' => ['view', 'program'],
        'communication' => ['view', 'program', 'sponsors'],
        'sponsoring' => ['view', 'sponsors'],
        'finance' => ['view', 'registrations', 'payments_record', 'payments_validate', 'payments_settings'],
        'registration' => ['view', 'tickets', 'registrations', 'payments_record'],
        'volunteer' => ['view'],
    ];

    /** Responsable organisationnel (ou organisateur) : tous les droits sur l'événement. */
    public static function isOrganizer(User $user, FoundationEvent $event): bool
    {
        if ($event->organizer_id !== null && $event->organizer_id === $user->id) {
            return true;
        }

        $context = new AdminContext($user);

        return $context->canManageScope('event.manage', $event->scope)
            && MembershipResponsibility::of($user, 'event.manage')->covers($event->scope, $event->node_id);
    }

    public static function role(User $user, FoundationEvent $event): ?string
    {
        return EventTeamMember::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->value('role');
    }

    public static function can(User $user, FoundationEvent $event, string $ability): bool
    {
        if (self::isOrganizer($user, $event)) {
            return true;
        }

        $role = self::role($user, $event);

        return $role !== null && in_array($ability, self::ROLE_ABILITIES[$role] ?? [], true);
    }

    /** Au moins l'accès en lecture (organisateur ou OC). */
    public static function canView(User $user, FoundationEvent $event): bool
    {
        return self::can($user, $event, 'view');
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(User $user, FoundationEvent $event): array
    {
        if (self::isOrganizer($user, $event)) {
            return array_keys(self::ABILITIES);
        }

        $role = self::role($user, $event);

        return $role !== null ? (self::ROLE_ABILITIES[$role] ?? []) : [];
    }
}
