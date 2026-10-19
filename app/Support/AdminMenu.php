<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\ModerationReport;

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
                    ['label' => 'Journal d\'audit', 'route' => 'admin.audit.index', 'icon' => 'activity', 'match' => 'admin.audit.*', 'perms' => ['entity.manage']],
                ],
            ],
            [
                'title' => 'Organisation',
                'items' => [
                    ['label' => 'Entités', 'route' => 'admin.entities.index', 'icon' => 'building', 'match' => 'admin.entities.*', 'perms' => ['entity.manage']],
                    ['label' => 'Organigramme', 'route' => 'orgchart.index', 'icon' => 'network', 'match' => 'orgchart.*', 'perms' => ['entity.manage']],
                    ['label' => 'Départements', 'route' => 'admin.departments.index', 'icon' => 'layers', 'match' => 'admin.departments.*', 'perms' => ['entity.manage']],
                    ['label' => 'Institutions', 'route' => 'admin.institutions.index', 'icon' => 'graduation', 'match' => 'admin.institutions.*', 'perms' => ['entity.manage']],
                    ['label' => 'Clubs', 'route' => 'admin.clubs.index', 'icon' => 'users', 'match' => 'admin.clubs.*', 'perms' => ['entity.manage']],
                ],
            ],
            [
                'title' => 'Communauté',
                'items' => [
                    ['label' => 'Utilisateurs', 'route' => 'admin.users.index', 'icon' => 'user', 'match' => 'admin.users.*', 'perms' => ['entity.manage', 'role.manage', 'membership.manage']],
                    ['label' => 'Adhésions', 'route' => 'admin.memberships.index', 'icon' => 'user-check', 'match' => 'admin.memberships.*', 'perms' => ['membership.manage'], 'badge' => 'memberships'],
                    ['label' => 'Rôles & permissions', 'route' => 'admin.roles.index', 'icon' => 'key', 'match' => 'admin.roles.*', 'perms' => ['role.manage']],
                ],
            ],
            [
                'title' => 'Contenu',
                'items' => [
                    ['label' => 'Publications', 'route' => 'admin.posts.index', 'icon' => 'file-text', 'match' => 'admin.posts.*', 'perms' => ['post.moderate']],
                    ['label' => 'Modération', 'route' => 'admin.moderation.index', 'icon' => 'flag', 'match' => 'admin.moderation.*', 'perms' => ['post.moderate'], 'badge' => 'reports'],
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
                ->when($context->scope('membership.manage') ?? [], fn ($query, array $ids) => $query->whereIn('entity_id', $ids))
                ->count(),
            'approvals' => ApprovalRequest::query()
                ->where('status', 'pending')
                ->where(function ($query) use ($context): void {
                    $query->whereNull('assigned_reviewer_id')->orWhere('assigned_reviewer_id', $context->user->id);
                })
                ->count(),
            'reports' => ModerationReport::query()->where('status', 'pending')->count(),
            default => 0,
        };

        return $count > 0 ? $count : null;
    }
}
