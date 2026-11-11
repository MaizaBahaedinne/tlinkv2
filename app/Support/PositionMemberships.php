<?php

namespace App\Support;

use App\Models\Mandate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Support\Cards\MemberCards;

/**
 * Tout titulaire d'un poste de l'organigramme du mandat en cours est membre de ce mandat, et validé :
 * s'il n'a pas encore d'adhésion pour le mandat, une adhésion approuvée est créée à partir de son poste.
 */
final class PositionMemberships
{
    /**
     * @return int nombre d'adhésions créées
     */
    public static function sync(?int $userId = null): int
    {
        $mandate = MemberCards::currentMandate();

        if ($mandate === null) {
            return 0;
        }

        $assignments = OrgAssignment::query()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->whereHas('person', fn ($person) => $person->whereNotNull('user_id')->when($userId !== null, fn ($query) => $query->where('user_id', $userId)))
            ->with('person:id,user_id')
            ->orderBy('id')
            ->get();

        if ($assignments->isEmpty()) {
            return 0;
        }

        $nodes = OrgNode::query()->get(['id', 'parent_id', 'kind', 'scope', 'title'])->keyBy('id');
        $roots = Mandate::query()->whereNotNull('root_node_id')->pluck('id', 'root_node_id')->all();
        $created = 0;
        $done = [];

        foreach ($assignments as $assignment) {
            $uid = (int) $assignment->person->user_id;

            if (isset($done[$uid]) || self::mandateOf((int) $assignment->node_id, $nodes, $roots, $mandate) !== $mandate->id) {
                continue;
            }

            $done[$uid] = true;
            [$scope, $clubId] = self::placement((int) $assignment->node_id, $nodes);

            if ($scope === null || ! OrgScopes::valid($scope)) {
                continue;
            }

            $exists = Membership::query()->where('user_id', $uid)
                ->whereIn('status', ['pending', 'approved', 'suspended'])
                ->inMandate($mandate)
                ->exists();

            if ($exists) {
                continue;
            }

            $start = $assignment->starts_at !== null && $assignment->starts_at->betweenIncluded($mandate->windowStart(), $mandate->windowEnd())
                ? $assignment->starts_at
                : $mandate->windowStart();

            Membership::query()->create([
                'user_id' => $uid,
                'scope' => $scope,
                'node_id' => $clubId,
                'position_id' => $assignment->node_id,
                'status' => 'approved',
                // Le rôle d'une adhésion est son type (comme à l'import legacy) : le poste, jamais « member » pour un responsable.
                'membership_type' => self::roleOf($nodes[(int) $assignment->node_id] ?? null),
                'starts_at' => $start->toDateString(),
                'approved_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }

    /** Rôle affiché d'une adhésion issue d'un poste : le titre du poste (60 caractères au plus, comme la colonne). */
    public static function roleOf(?OrgNode $position): string
    {
        $title = trim((string) $position?->title);

        return $title === '' ? 'member' : mb_substr($title, 0, 60);
    }

    /**
     * Mandat dont l'organigramme contient le bloc ; un bloc hors de tout mandat suit le mandat en cours.
     *
     * @param \Illuminate\Support\Collection<int, OrgNode> $nodes
     * @param array<int, int> $roots racine de mandat => id du mandat
     */
    private static function mandateOf(int $nodeId, $nodes, array $roots, Mandate $current): int
    {
        $id = $nodeId;

        for ($guard = 0; $id !== null && $guard < 80; $guard++) {
            if (isset($roots[$id])) {
                return (int) $roots[$id];
            }

            $id = $nodes[$id]->parent_id ?? null;
        }

        return $current->id;
    }

    /**
     * Périmètre du bloc (le sien ou celui du plus proche parent) et club qui le contient.
     *
     * @param \Illuminate\Support\Collection<int, OrgNode> $nodes
     * @return array{0: ?string, 1: ?int}
     */
    private static function placement(int $nodeId, $nodes): array
    {
        $scope = null;
        $club = null;
        $id = $nodeId;

        for ($guard = 0; $id !== null && $guard < 80; $guard++) {
            $node = $nodes[$id] ?? null;

            if ($node === null) {
                break;
            }

            $club ??= $node->kind === 'club' ? (int) $node->id : null;

            if ($node->scope !== null) {
                $scope = (string) $node->scope;
                break;
            }

            $id = $node->parent_id;
        }

        return [$scope, $club];
    }
}
