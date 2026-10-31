<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\ContactMessage;
use App\Models\Membership;
use App\Models\MentoringTicket;
use App\Models\ModerationReport;
use App\Models\PositionApplication;
use App\Support\Mentoring\Mentoring;

/**
 * Construit la navigation de l'espace admin en fonction des permissions de l'utilisateur.
 */
class AdminMenu
{
    /**
     * Module du catalogue qui commande chaque entrée (clé : motif de route de l'entrée).
     * Une entrée sans module n'est commandée que par le niveau d'accès du poste.
     */
    public const MODULES = [
        'admin.roadmap.*' => 'governance.roadmap',
        'admin.mandates.*' => 'organization.mandates',
        'admin.launch.*' => 'website.launch',
        'admin.modules.*' => 'governance.modules',
        'admin.audit.*' => 'governance.audit',
        'orgchart.*' => 'organization.chart',
        'admin.postes.*' => 'organization.positions',
        'admin.candidatures.*' => 'organization.applications',
        'admin.clubs.*' => 'organization.clubs',
        'admin.institutions.*' => 'organization.institutions',
        'admin.roles.*' => 'organization.positions',
        'admin.scopes.*' => 'organization.scopes',
        'admin.users.*' => 'members.users',
        'admin.memberships.*' => 'members.memberships',
        'admin.members.*' => 'members.entity_members',
        'admin.posts.*' => 'governance.moderation',
        'admin.moderation.*' => 'governance.moderation',
        'admin.site.*' => 'website.content',
        'admin.partners.*' => 'website.partners',
        'admin.projects.*' => 'website.projects',
        'admin.news.*' => 'website.news',
        'admin.info-session.*' => 'members.info_session',
        'admin.messages.*' => 'website.messages',
        'admin.newsletter.*' => 'website.newsletter',
        'admin.approvals.*' => 'governance.approvals',
        'admin.events.*' => 'events.events',
        'admin.badges.*' => 'gamification.badges',
        'admin.companies.*' => 'members.companies',
        'admin.verifications.*' => 'members.verification',
        'admin.academy.courses.*' => 'academy.courses',
        'admin.academy.sessions.*' => 'academy.sessions',
        'admin.academy.orders.*' => 'academy.orders',
        'admin.academy.settings.*' => 'academy.plans',
        'admin.store.index' => 'store.orders',
        'admin.store.products.*' => 'store.catalog',
        'admin.store.reviews.*' => 'store.catalog',
        'admin.store.packs.*' => 'store.catalog',
        'admin.store.refs.*' => 'store.catalog',
        'admin.store.stock.*' => 'store.stock',
        'admin.store.orders.*' => 'store.orders',
        'admin.store.returns.*' => 'store.returns',
        'admin.store.settings.*' => 'store.settings',
        'admin.mentoring.tickets.*' => 'mentoring.tickets',
        'admin.mentoring.mentors.*' => 'mentoring.tickets',
    ];

    /**
     * Entrée de menu qui couvre cette route : permissions du niveau d'accès et module commandé.
     *
     * @return array{perms: array<int, string>, module: string}|null
     */
    public static function itemFor(?string $routeName): ?array
    {
        if ($routeName === null) {
            return null;
        }

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                $module = self::MODULES[$item['match']] ?? null;

                if ($module !== null && $item['perms'] !== null && \Illuminate\Support\Str::is($item['match'], $routeName)) {
                    return ['perms' => $item['perms'], 'module' => $module];
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{title: string, items: array<int, array<string, mixed>>}>
     */
    private static function groups(): array
    {
        $groups = [
            [
                'title' => 'Pilotage',
                'items' => [
                    ['label' => 'Tableau de bord', 'route' => 'admin.dashboard', 'icon' => 'home', 'match' => 'admin.dashboard', 'perms' => null],
                    ['label' => 'Feuille de route', 'route' => 'admin.roadmap.index', 'icon' => 'map', 'match' => 'admin.roadmap.*', 'perms' => ['entity.manage']],
                    ['label' => 'Mandats', 'route' => 'admin.mandates.index', 'icon' => 'calendar', 'match' => 'admin.mandates.*', 'perms' => ['entity.manage']],
                    ['label' => 'Lancement du site', 'route' => 'admin.launch.edit', 'icon' => 'flag', 'match' => 'admin.launch.*', 'perms' => ['entity.manage']],
                    ['label' => 'Modules de l\'application', 'route' => 'admin.modules.index', 'icon' => 'network', 'match' => 'admin.modules.*', 'perms' => ['entity.manage']],
                    ['label' => 'Journal d\'audit', 'route' => 'admin.audit.index', 'icon' => 'activity', 'match' => 'admin.audit.*', 'perms' => ['entity.manage']],
                ],
            ],
            [
                'title' => 'Organisation',
                'items' => [
                    ['label' => 'Organigramme', 'route' => 'orgchart.index', 'icon' => 'network', 'match' => 'orgchart.*', 'perms' => ['entity.manage']],
                    ['label' => 'Postes et droits', 'route' => 'admin.postes.index', 'icon' => 'key', 'match' => 'admin.postes.*', 'perms' => ['entity.manage']],
                    ['label' => 'Candidatures aux postes', 'route' => 'admin.candidatures.index', 'icon' => 'user-check', 'match' => 'admin.candidatures.*', 'perms' => ['entity.manage'], 'badge' => 'applications'],
                    ['label' => 'Clubs', 'route' => 'admin.clubs.index', 'icon' => 'building', 'match' => 'admin.clubs.*', 'perms' => ['entity.manage']],
                    ['label' => 'Établissements', 'route' => 'admin.institutions.index', 'icon' => 'graduation', 'match' => 'admin.institutions.*', 'perms' => ['entity.manage']],
                    ['label' => 'Droits des clubs', 'route' => 'admin.roles.index', 'icon' => 'shield', 'match' => 'admin.roles.*', 'perms' => ['entity.manage']],
                    ['label' => 'Périmètres', 'route' => 'admin.scopes.edit', 'icon' => 'network', 'match' => 'admin.scopes.*', 'perms' => ['entity.manage']],
                ],
            ],
            [
                'title' => 'Communauté',
                'items' => [
                    ['label' => 'Utilisateurs', 'route' => 'admin.users.index', 'icon' => 'user', 'match' => 'admin.users.*', 'perms' => ['entity.manage', 'membership.manage']],
                    ['label' => 'Adhésions', 'route' => 'admin.memberships.index', 'icon' => 'user-check', 'match' => 'admin.memberships.*', 'perms' => ['membership.manage'], 'badge' => 'memberships'],
                    ['label' => 'Membres de mon entité', 'route' => 'admin.members.index', 'icon' => 'user', 'match' => 'admin.members.*', 'perms' => ['membership.manage']],
                    ['label' => 'Badges et missions', 'route' => 'admin.badges.index', 'icon' => 'award', 'match' => 'admin.badges.*', 'perms' => ['entity.manage']],
                    ['label' => 'Entreprises', 'route' => 'admin.companies.index', 'icon' => 'building', 'match' => 'admin.companies.*', 'perms' => ['entity.manage']],
                    ['label' => 'Vérifications d’identité', 'route' => 'admin.verifications.index', 'icon' => 'shield', 'match' => 'admin.verifications.*', 'perms' => ['entity.manage'], 'badge' => 'verifications'],
                ],
            ],
            [
                'title' => 'Contenu',
                'items' => [
                    ['label' => 'Publications', 'route' => 'admin.posts.index', 'icon' => 'file-text', 'match' => 'admin.posts.*', 'perms' => ['post.moderate']],
                    ['label' => 'Modération', 'route' => 'admin.moderation.index', 'icon' => 'flag', 'match' => 'admin.moderation.*', 'perms' => ['post.moderate'], 'badge' => 'reports'],
                    ['label' => 'Site public', 'route' => 'admin.site.edit', 'icon' => 'external', 'match' => 'admin.site.*', 'perms' => ['entity.manage']],
                    ['label' => 'Partenaires', 'route' => 'admin.partners.index', 'icon' => 'building', 'match' => 'admin.partners.*', 'perms' => ['entity.manage']],
                    ['label' => 'Projets', 'route' => 'admin.projects.index', 'icon' => 'layers', 'match' => 'admin.projects.*', 'perms' => ['entity.manage']],
                    ['label' => 'Actualités du site', 'route' => 'admin.news.index', 'icon' => 'file-text', 'match' => 'admin.news.*', 'perms' => ['entity.manage']],
                    ['label' => 'Info session et quiz', 'route' => 'admin.info-session.edit', 'icon' => 'graduation', 'match' => 'admin.info-session.*', 'perms' => ['entity.manage']],
                    ['label' => 'Messages du site', 'route' => 'admin.messages.index', 'icon' => 'file-text', 'match' => 'admin.messages.*', 'perms' => ['entity.manage'], 'badge' => 'messages'],
                    ['label' => 'Newsletter', 'route' => 'admin.newsletter.index', 'icon' => 'user', 'match' => 'admin.newsletter.*', 'perms' => ['entity.manage']],
                ],
            ],
            [
                'title' => 'Tunivisions Academy',
                'items' => [
                    ['label' => 'Cours', 'route' => 'admin.academy.courses.index', 'icon' => 'graduation', 'match' => 'admin.academy.courses.*', 'perms' => ['entity.manage']],
                    ['label' => 'Calendrier des sessions', 'route' => 'admin.academy.sessions.index', 'icon' => 'calendar', 'match' => 'admin.academy.sessions.*', 'perms' => ['event.manage']],
                    ['label' => 'Commandes et paiements', 'route' => 'admin.academy.orders.index', 'icon' => 'file-text', 'match' => 'admin.academy.orders.*', 'perms' => ['entity.manage']],
                    ['label' => 'Abonnements et réglages', 'route' => 'admin.academy.settings.edit', 'icon' => 'layers', 'match' => 'admin.academy.settings.*', 'perms' => ['entity.manage']],
                ],
            ],
            [
                'title' => 'Tunivisions Stores',
                'items' => [
                    ['label' => 'Vue d’ensemble', 'route' => 'admin.store.index', 'icon' => 'home', 'match' => 'admin.store.index', 'perms' => ['entity.manage']],
                    ['label' => 'Commandes', 'route' => 'admin.store.orders.index', 'icon' => 'shopping-bag', 'match' => 'admin.store.orders.*', 'perms' => ['entity.manage'], 'badge' => 'store_orders'],
                    ['label' => 'Retours', 'route' => 'admin.store.returns.index', 'icon' => 'inbox', 'match' => 'admin.store.returns.*', 'perms' => ['entity.manage'], 'badge' => 'store_returns'],
                    ['label' => 'Articles', 'route' => 'admin.store.products.index', 'icon' => 'package', 'match' => 'admin.store.products.*', 'perms' => ['entity.manage']],
                    ['label' => 'Packs', 'route' => 'admin.store.packs.index', 'icon' => 'layers', 'match' => 'admin.store.packs.*', 'perms' => ['entity.manage']],
                    ['label' => 'Stock', 'route' => 'admin.store.stock.index', 'icon' => 'activity', 'match' => 'admin.store.stock.*', 'perms' => ['entity.manage']],
                    ['label' => 'Catégories, couleurs, tailles', 'route' => 'admin.store.refs.index', 'icon' => 'file-text', 'match' => 'admin.store.refs.*', 'perms' => ['entity.manage']],
                    ['label' => 'Réglages, livraison, paiement', 'route' => 'admin.store.settings.edit', 'icon' => 'key', 'match' => 'admin.store.settings.*', 'perms' => ['entity.manage']],
                ],
            ],
            [
                'title' => 'Mentorat',
                'items' => [
                    ['label' => 'Tickets de mentorat', 'route' => 'admin.mentoring.tickets.index', 'icon' => 'file-text', 'match' => 'admin.mentoring.tickets.*', 'perms' => ['mentoring.manage'], 'badge' => 'mentoring'],
                    ['label' => 'Mentors', 'route' => 'admin.mentoring.mentors.index', 'icon' => 'users', 'match' => 'admin.mentoring.mentors.*', 'perms' => ['mentoring.manage', 'mentoring.roster']],
                ],
            ],
            [
                'title' => 'Gouvernance',
                'items' => [
                    ['label' => 'Approbations', 'route' => 'admin.approvals.index', 'icon' => 'shield', 'match' => 'admin.approvals.*', 'perms' => ['approval.review'], 'badge' => 'approvals'],
                    ['label' => 'Événements', 'route' => 'admin.events.index', 'icon' => 'calendar', 'match' => 'admin.events.*', 'perms' => ['event.manage']],
                ],
            ],
        ];

        return $groups;
    }

    /**
     * @return array<int, array{title: string, items: array<int, array{label: string, route: string, icon: string, match: string, badge: int|null}>}>
     */
    public static function build(AdminContext $context): array
    {
        $groups = self::groups();
        $result = [];

        foreach ($groups as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if ($item['perms'] !== null && ! self::visible($item, $context)) {
                    continue;
                }

                $items[] = [
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'icon' => $item['icon'],
                    'match' => $item['match'],
                    'badge' => isset($item['badge']) ? self::badge($item['badge'], $context) : null,
                ];
            }

            if ($items !== []) {
                $result[] = ['title' => $group['title'], 'items' => $items];
            }
        }

        return $result;
    }

    /**
     * L'utilisateur voit-il au moins une entrée de l'administration (hors tableau de bord) ?
     * C'est la condition d'affichage de tous les liens « Administration » de l'application.
     */
    public static function hasAccess(AdminContext $context): bool
    {
        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                if ($item['perms'] !== null && self::visible($item, $context)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function visible(array $item, AdminContext $context): bool
    {
        $module = self::MODULES[$item['match']] ?? null;

        return $module === null
            ? $context->canAny($item['perms'])
            : $context->allowsModule($item['perms'], $module);
    }

    private static function badge(string $type, AdminContext $context): ?int
    {
        $count = match ($type) {
            'memberships' => ($context->scope('membership.manage') ?? []) === []
                ? Membership::query()->where('status', 'pending')->count()
                : MembershipResponsibility::of($context->user, 'membership.manage')
                    ->apply(Membership::query()->where('status', 'pending'))
                    ->count(),
            'approvals' => ApprovalRequest::query()
                ->where('status', 'pending')
                ->where(function ($query) use ($context): void {
                    $query->whereNull('assigned_reviewer_id')->orWhere('assigned_reviewer_id', $context->user->id);
                })
                ->count(),
            'reports' => ModerationReport::query()->where('status', 'pending')->count(),
            'messages' => ContactMessage::query()->where('status', 'new')->count(),
            'applications' => PositionApplication::query()->where('status', 'pending')->count(),
            'verifications' => \App\Models\IdentityVerification::query()->where('status', 'pending')->count(),
            'mentoring' => Mentoring::enabled() ? MentoringTicket::query()->where('status', 'new')->count() : 0,
            'store_orders' => \App\Support\Store\StoreSettings::tablesReady() ? \App\Models\StoreOrder::query()->where('status', 'pending')->count() : 0,
            'store_returns' => \App\Support\Store\StoreSettings::tablesReady() ? \App\Models\StoreReturn::query()->where('status', 'requested')->count() : 0,
            default => 0,
        };

        return $count > 0 ? $count : null;
    }
}
