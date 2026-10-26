<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\ContactMessage;
use App\Models\Membership;
use App\Models\ModerationReport;
use App\Models\PositionApplication;

/**
 * Construit la navigation de l'espace admin en fonction des permissions de l'utilisateur.
 */
class AdminMenu
{
    /**
     * @return array<int, array{title: string, items: array<int, array{label: string, route: string, icon: string, match: string, badge: int|null}>}>
     */
    public static function build(AdminContext $context): array
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
                    ['label' => 'Droits des clubs', 'route' => 'admin.roles.index', 'icon' => 'shield', 'match' => 'admin.roles.*', 'perms' => ['entity.manage']],
                    ['label' => 'Périmètres', 'route' => 'admin.scopes.edit', 'icon' => 'network', 'match' => 'admin.scopes.*', 'perms' => ['entity.manage']],
                ],
            ],
            [
                'title' => 'Communauté',
                'items' => [
                    ['label' => 'Utilisateurs', 'route' => 'admin.users.index', 'icon' => 'user', 'match' => 'admin.users.*', 'perms' => ['entity.manage', 'membership.manage']],
                    ['label' => 'Adhésions', 'route' => 'admin.memberships.index', 'icon' => 'user-check', 'match' => 'admin.memberships.*', 'perms' => ['membership.manage'], 'badge' => 'memberships'],
                ],
            ],
            [
                'title' => 'Contenu',
                'items' => [
                    ['label' => 'Publications', 'route' => 'admin.posts.index', 'icon' => 'file-text', 'match' => 'admin.posts.*', 'perms' => ['post.moderate']],
                    ['label' => 'Modération', 'route' => 'admin.moderation.index', 'icon' => 'flag', 'match' => 'admin.moderation.*', 'perms' => ['post.moderate'], 'badge' => 'reports'],
                    ['label' => 'Site public', 'route' => 'admin.site.edit', 'icon' => 'external', 'match' => 'admin.site.*', 'perms' => ['entity.manage']],
                    ['label' => 'Partenaires', 'route' => 'admin.partners.index', 'icon' => 'building', 'match' => 'admin.partners.*', 'perms' => ['entity.manage']],
                    ['label' => 'Info session et quiz', 'route' => 'admin.info-session.edit', 'icon' => 'graduation', 'match' => 'admin.info-session.*', 'perms' => ['entity.manage']],
                    ['label' => 'Messages du site', 'route' => 'admin.messages.index', 'icon' => 'file-text', 'match' => 'admin.messages.*', 'perms' => ['entity.manage'], 'badge' => 'messages'],
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

        $result = [];

        foreach ($groups as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if ($item['perms'] !== null && ! $context->canAny($item['perms'])) {
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

    private static function badge(string $type, AdminContext $context): ?int
    {
        $count = match ($type) {
            'memberships' => Membership::query()
                ->where('status', 'pending')
                ->when($context->scope('membership.manage') ?? [], fn ($query, array $scopes) => $query->whereIn('scope', $scopes))
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
            default => 0,
        };

        return $count > 0 ? $count : null;
    }
}
