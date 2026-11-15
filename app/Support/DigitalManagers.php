<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Support\OrgChart\OrgChartInstaller;

/**
 * Digital manager de la Fondation : poste système (sous le directeur exécutif) qui valide ou refuse les demandes d'accès
 * aux comptes marketing, gère la charte d'usage des réseaux sociaux et attribue les portfolios Meta.
 */
final class DigitalManagers
{
    public const KEY = 'digital-manager';

    public const MATRIX = [
        'marketing.accounts' => ['create', 'read', 'update', 'delete'],
        'marketing.requests' => ['create', 'read', 'delete'],
        'marketing.review' => ['read', 'update'],
    ];

    /** Crée le poste (idempotent) avec ses droits sur toute la plateforme. Sans organigramme installé, ne fait rien. */
    public static function ensurePosition(): ?OrgNode
    {
        $existing = OrgNode::query()->where('system_key', self::KEY)->first();

        if ($existing !== null) {
            return $existing;
        }

        $director = OrgNode::query()->where('system_key', 'executive-director')->first();

        if ($director === null) {
            return null;
        }

        ModuleCatalog::sync();

        $node = OrgNode::query()->create([
            'parent_id' => $director->id,
            'category' => 'direction',
            'title' => 'Digital manager de la Fondation',
            'subtitle' => 'Communication digitale',
            'description' => 'Valide ou refuse les demandes d’accès aux comptes Facebook et Instagram des entités, tient la charte d’usage des réseaux sociaux et attribue les portfolios Meta.',
            'capacity' => 'single',
            'system_key' => self::KEY,
            'sort_order' => (int) OrgNode::query()->where('parent_id', $director->id)->max('sort_order') + 1,
        ]);

        ModulePermissions::apply($node, self::MATRIX, true);

        return $node->refresh();
    }

    /**
     * Comptes des personnes qui valident les demandes : titulaires actuels d'un poste dont la matrice permet de modifier « marketing.review ».
     *
     * @return array<int, int>
     */
    public static function userIds(): array
    {
        $nodeIds = OrgNode::query()->where('kind', 'position')->whereNotNull('module_permissions')->get(['id', 'module_permissions'])
            ->filter(fn (OrgNode $node): bool => in_array('update', (array) (((array) $node->module_permissions)['marketing.review'] ?? []), true))
            ->pluck('id')->all();

        if ($nodeIds === []) {
            return [];
        }

        return OrgAssignment::query()
            ->whereIn('node_id', $nodeIds)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->with('person:id,user_id')->get()
            ->map(fn (OrgAssignment $assignment): ?int => $assignment->person?->user_id)
            ->filter()->unique()->map(fn ($id): int => (int) $id)->values()->all();
    }

    /** Notifie un ou plusieurs comptes (dans T.Link). */
    public static function notify(array $userIds, string $title, string $body, string $url, string $type = 'marketing_access'): void
    {
        foreach (array_unique($userIds) as $userId) {
            AppNotification::query()->create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body, 'data' => ['url' => $url]]);
        }
    }
}
