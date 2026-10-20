<?php

namespace App\Support;

use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;

/**
 * Droits d'un utilisateur : ils viennent uniquement des postes qu'il occupe dans l'organigramme.
 * Source unique pour AdminContext, AccessScope, ScopeResolver et ApprovalResolver.
 */
class OrgAccess
{
    /**
     * Permissions de l'utilisateur et leur perimetre : slug => liste d'identifiants d'entite (null = global).
     * Un poste sans entite (explicite ou heritee) n'accorde jamais de droit : pas d'elevation par defaut.
     *
     * @return array<string, array<int, int|null>>
     */
    public static function grants(User $user): array
    {
        $assignments = self::currentAssignments()
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))
            ->get();

        return self::collect($assignments);
    }

    /**
     * Utilisateurs qui detiennent une permission pour une entite, dans l'ordre d'attribution.
     *
     * @return array<int, int>
     */
    public static function userIdsWith(string $permission, int $entityId, int $exceptUserId, ?string $level = null): array
    {
        $assignments = self::currentAssignments()
            ->when($level !== null, fn ($query) => $query->whereHas('node', fn ($node) => $node->where('access_level', $level)))
            ->whereHas('person', fn ($query) => $query->whereNotNull('user_id')->where('user_id', '!=', $exceptUserId))
            ->with('person:id,user_id')
            ->orderBy('id')
            ->get();

        $ids = [];

        foreach ($assignments as $assignment) {
            $scopes = self::collect(collect([$assignment]))[$permission] ?? [];

            if (in_array(null, $scopes, true) || in_array($entityId, $scopes, true)) {
                $ids[] = (int) $assignment->person->user_id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Entite effective d'un bloc : la sienne, sinon celle du plus proche parent.
     */
    public static function entityOf(OrgNode $node): ?int
    {
        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $entities = OrgNode::query()->whereNotNull('entity_id')->pluck('entity_id', 'id')->all();

        return self::resolveEntity($node->id, $parents, $entities);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<OrgAssignment>
     */
    private static function currentAssignments()
    {
        $today = now()->toDateString();

        return OrgAssignment::query()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->whereHas('node', fn ($query) => $query->where('kind', 'position')->where('access_level', '!=', AccessLevels::NONE))
            ->with('node:id,parent_id,access_level,entity_id');
    }

    /**
     * @param \Illuminate\Support\Collection<int, OrgAssignment> $assignments
     * @return array<string, array<int, int|null>>
     */
    private static function collect($assignments): array
    {
        if ($assignments->isEmpty()) {
            return [];
        }

        $parents = null;
        $entities = null;
        $grants = [];

        foreach ($assignments as $assignment) {
            $node = $assignment->node;
            $level = (string) $node?->access_level;
            $permissions = AccessLevels::permissions($level);

            if ($permissions === []) {
                continue;
            }

            if (AccessLevels::isGlobal($level)) {
                $scope = null;
            } else {
                $parents ??= OrgNode::query()->pluck('parent_id', 'id')->all();
                $entities ??= OrgNode::query()->whereNotNull('entity_id')->pluck('entity_id', 'id')->all();
                $scope = self::resolveEntity($node->id, $parents, $entities);

                if ($scope === null) {
                    continue;
                }
            }

            foreach ($permissions as $permission) {
                $grants[$permission][] = $scope;
            }
        }

        return $grants;
    }

    /**
     * @param array<int, int|null> $parents
     * @param array<int, int> $entities
     */
    private static function resolveEntity(int $nodeId, array $parents, array $entities): ?int
    {
        for ($guard = 0; $guard < 80 && $nodeId !== null; $guard++) {
            if (isset($entities[$nodeId])) {
                return (int) $entities[$nodeId];
            }

            $nodeId = $parents[$nodeId] ?? null;
        }

        return null;
    }
}
