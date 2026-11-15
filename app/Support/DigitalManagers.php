<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;

/**
 * Personnes qui valident les demandes d'accès aux comptes marketing : les titulaires de tout poste dont la matrice
 * accorde « Gestion des demandes d’accès » (marketing.review). Aucun poste n'est créé : on l'affecte dans l'organigramme.
 */
final class DigitalManagers
{
    /**
     * Comptes des titulaires actuels d'un poste dont la matrice accorde explicitement « marketing.review » (modifier par défaut).
     * Un poste sans matrice n'en fait pas partie : la validation ne s'obtient pas par défaut.
     *
     * @return array<int, int>
     */
    public static function userIds(string $action = 'update'): array
    {
        $actions = $action === 'read' ? ['read', 'update'] : ['update'];

        $nodeIds = OrgNode::query()->where('kind', 'position')->whereNotNull('module_permissions')->get(['id', 'module_permissions'])
            ->filter(fn (OrgNode $node): bool => array_intersect($actions, (array) (((array) $node->module_permissions)['marketing.review'] ?? [])) !== [])
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

    /** Peut valider les demandes : administrateur global, ou poste à qui la matrice accorde « Gestion des demandes d’accès ». */
    public static function canReview(User $user, string $action = 'read'): bool
    {
        return (new AdminContext($user))->isGlobal('entity.manage') || in_array($user->id, self::userIds($action), true);
    }

    /** Notifie un ou plusieurs comptes (dans T.Link). */
    public static function notify(array $userIds, string $title, string $body, string $url, string $type = 'marketing_access'): void
    {
        foreach (array_unique($userIds) as $userId) {
            AppNotification::query()->create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body, 'data' => ['url' => $url]]);
        }
    }
}
