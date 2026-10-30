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
     * Permissions de l'utilisateur et leur perimetre : slug => liste de perimetres (null = global).
     * Un poste sans perimetre (explicite ou herite) n'accorde jamais de droit : pas d'elevation par defaut.
     *
     * @return array<string, array<int, string|null>>
     */
    public static function grants(User $user): array
    {
        return self::aggregate(self::positions($user));
    }

    /**
     * Postes qui accordent des droits à l'utilisateur : permissions, périmètre (null = global) et matrice CRUD
     * par module (null = pas de matrice, le niveau d'accès du poste s'applique sans restriction).
     *
     * @return array<int, array{permissions: array<int, string>, scope: string|null, matrix: array<string, array<int, string>>|null}>
     */
    public static function positions(User $user): array
    {
        $assignments = self::currentAssignments()
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))
            ->get();

        return self::describeAll($assignments);
    }

    /**
     * @param array<int, array{permissions: array<int, string>, scope: string|null, matrix: mixed}> $positions
     * @return array<string, array<int, string|null>>
     */
    public static function aggregate(array $positions): array
    {
        $grants = [];

        foreach ($positions as $position) {
            foreach ($position['permissions'] as $permission) {
                $grants[$permission][] = $position['scope'];
            }
        }

        return $grants;
    }

    /**
     * Utilisateurs qui detiennent une permission pour un perimetre, dans l'ordre d'attribution.
     *
     * @return array<int, int>
     */
    public static function userIdsWith(string $permission, string $scope, int $exceptUserId, ?string $level = null): array
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

            $canCover = in_array(null, $scopes, true);

            if (! $canCover) {
                foreach ($scopes as $granted) {
                    if (is_string($granted) && OrgScopes::covers($granted, $scope)) {
                        $canCover = true;
                        break;
                    }
                }
            }

            if ($canCover) {
                $ids[] = (int) $assignment->person->user_id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Droits qu'accorde un poste (permission => perimetres, null = global), quel que soit son titulaire.
     *
     * @return array<string, array<int, string|null>>
     */
    public static function nodeGrants(OrgNode $node): array
    {
        $assignment = (new OrgAssignment())->setRelation('node', $node);

        return self::collect(collect([$assignment]));
    }

    /**
     * Responsabilite d'un utilisateur pour une permission : tout (droits globaux), des perimetres entiers
     * (postes hors club) et des clubs precis (postes a l'interieur d'un club : leur responsabilite s'arrete au club).
     *
     * @return array{all: bool, scopes: array<int, string>, clubs: array<int, int>}
     */
    public static function responsibility(User $user, string $permission): array
    {
        $assignments = self::currentAssignments()
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))
            ->get();

        $result = ['all' => false, 'scopes' => [], 'clubs' => []];

        if ($assignments->isEmpty()) {
            return $result;
        }

        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $kinds = OrgNode::query()->pluck('kind', 'id')->all();
        $scopes = OrgNode::query()->whereNotNull('scope')->pluck('scope', 'id')->all();

        foreach ($assignments as $assignment) {
            $primary = self::resolveScope((int) $assignment->node_id, $parents, $scopes);
            $club = self::clubOf((int) $assignment->node_id, $parents, $kinds);

            foreach (self::describeAll(collect([$assignment])) as $entry) {
                if (! in_array($permission, $entry['permissions'], true)) {
                    continue;
                }

                if ($entry['scope'] === null) {
                    $result['all'] = true;
                } elseif ($club !== null && $entry['scope'] === $primary) {
                    $result['clubs'][] = $club;
                } else {
                    $result['scopes'][] = $entry['scope'];
                }
            }
        }

        $result['scopes'] = OrgScopes::expandManaged(array_values(array_unique($result['scopes'])));
        $result['clubs'] = array_values(array_unique($result['clubs']));

        return $result;
    }

    /**
     * Titulaires d'un poste situe dans le club qui detiennent la permission, le president de club d'abord.
     *
     * @return array<int, int>
     */
    public static function userIdsInClub(string $permission, int $clubId, int $exceptUserId): array
    {
        $assignments = self::currentAssignments()
            ->whereHas('person', fn ($query) => $query->whereNotNull('user_id')->where('user_id', '!=', $exceptUserId))
            ->with('person:id,user_id')
            ->orderBy('id')
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $kinds = OrgNode::query()->pluck('kind', 'id')->all();
        $rows = [];

        foreach ($assignments as $assignment) {
            if (self::clubOf((int) $assignment->node_id, $parents, $kinds) !== $clubId) {
                continue;
            }

            foreach (self::describeAll(collect([$assignment])) as $entry) {
                if (in_array($permission, $entry['permissions'], true)) {
                    $priority = ['club_president' => 0, 'manager' => 1][(string) $assignment->node?->access_level] ?? 2;
                    $rows[] = [$priority, (int) $assignment->id, (int) $assignment->person->user_id];
                    break;
                }
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_values(array_unique(array_column($rows, 2)));
    }

    /**
     * Club qui contient un bloc : son plus proche ancetre de type club (null hors d'un club).
     *
     * @param array<int, int|null> $parents
     * @param array<int, string> $kinds
     */
    private static function clubOf(int $nodeId, array $parents, array $kinds): ?int
    {
        $id = $parents[$nodeId] ?? null;

        for ($guard = 0; $id !== null && $guard < 80; $guard++) {
            if (($kinds[$id] ?? null) === 'club') {
                return (int) $id;
            }

            $id = $parents[$id] ?? null;
        }

        return null;
    }

    /**
     * Perimetre effectif d'un bloc : le sien, sinon celui du plus proche parent.
     */
    public static function scopeOf(OrgNode $node): ?string
    {
        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $scopes = OrgNode::query()->whereNotNull('scope')->pluck('scope', 'id')->all();

        return self::resolveScope($node->id, $parents, $scopes);
    }

    /**
     * Perimetre effectif de chaque bloc (id => perimetre ou null), calcule en une seule passe.
     *
     * @return array<int, string|null>
     */
    public static function scopeMap(): array
    {
        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $scopes = OrgNode::query()->whereNotNull('scope')->pluck('scope', 'id')->all();
        $map = [];

        foreach (array_keys($parents) as $id) {
            $map[$id] = self::resolveScope((int) $id, $parents, $scopes);
        }

        return $map;
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
            // Colonnes non listées : le code reste utilisable avant la migration de module_permissions.
            ->with('node');
    }

    /**
     * @param \Illuminate\Support\Collection<int, OrgAssignment> $assignments
     * @return array<string, array<int, string|null>>
     */
    private static function collect($assignments): array
    {
        return self::aggregate(self::describeAll($assignments));
    }

    /**
     * @param \Illuminate\Support\Collection<int, OrgAssignment> $assignments
     * @return array<int, array{permissions: array<int, string>, scope: string|null, matrix: array<string, array<int, string>>|null}>
     */
    private static function describeAll($assignments): array
    {
        if ($assignments->isEmpty()) {
            return [];
        }

        $parents = null;
        $scopes = null;
        $positions = [];

        foreach ($assignments as $assignment) {
            $node = $assignment->node;
            $level = (string) $node?->access_level;
            $custom = $node?->permissions;
            $permissions = AccessLevels::forPosition($level, $custom);

            if ($permissions === []) {
                continue;
            }

            if (AccessLevels::reachesEverywhere($level, $custom)) {
                $nodeScopes = [null];
            } else {
                $parents ??= OrgNode::query()->pluck('parent_id', 'id')->all();
                $scopes ??= OrgNode::query()->whereNotNull('scope')->pluck('scope', 'id')->all();
                $primary = self::resolveScope($node->id, $parents, $scopes);

                // Périmètre principal (propre ou hérité) puis périmètres supplémentaires du poste.
                $nodeScopes = array_values(array_unique(array_filter(
                    array_merge([$primary], (array) $node->extra_scopes),
                    fn ($scope) => is_string($scope) && OrgScopes::valid($scope),
                )));

                if ($nodeScopes === []) {
                    continue;
                }
            }

            $matrix = (array) $node->module_permissions;

            // L'administrateur global n'est jamais restreint par une matrice : il garde toutes les entrées de menu.
            $restricted = $level !== AccessLevels::GLOBAL_ADMIN && $matrix !== [];

            // Une entrée par périmètre : mêmes droits et même matrice sur chacun.
            foreach ($nodeScopes as $scope) {
                $positions[] = [
                    'permissions' => $permissions,
                    'scope' => $scope,
                    'matrix' => $restricted ? $matrix : null,
                ];
            }
        }

        return $positions;
    }

    /**
     * @param array<int, int|null> $parents
     * @param array<int, string> $scopes
     */
    private static function resolveScope(int $nodeId, array $parents, array $scopes): ?string
    {
        for ($guard = 0; $guard < 80 && $nodeId !== null; $guard++) {
            if (isset($scopes[$nodeId])) {
                return (string) $scopes[$nodeId];
            }

            $nodeId = $parents[$nodeId] ?? null;
        }

        return null;
    }
}
