<?php

namespace App\Support;

use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * « Mon bureau » : pour un membre qui occupe des postes, ses supérieurs (liens hiérarchiques vers le haut)
 * et son équipe (liens hiérarchiques vers le bas). Un supérieur gère les titulaires des postes situés sous les siens,
 * sans jamais pouvoir accorder plus de droits que les siens. Un simple rattachement n'est pas un lien hiérarchique.
 */
final class MyOffice
{
    public const MODULE = 'organization.my_office';

    private const MAX_TEAM_ROWS = 300;

    public static function enabled(): bool
    {
        return ModuleCatalog::isActive(self::MODULE);
    }

    /**
     * @return array<int, int>
     */
    public static function heldNodeIds(User $user): array
    {
        return OrgAssignment::query()
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->whereHas('person', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereHas('node', fn (Builder $query) => $query->where('kind', 'position'))
            ->pluck('node_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function holdsPosition(User $user): bool
    {
        return self::heldNodeIds($user) !== [];
    }

    /**
     * Le membre peut-il affecter ou retirer des titulaires sur ce poste ?
     * Le poste doit se trouver strictement sous un poste qu'il occupe (liens hiérarchiques uniquement)
     * et ne pas accorder de droits que le membre n'a pas lui-même.
     */
    public static function canManage(User $user, OrgNode $target): bool
    {
        if ($target->kind !== 'position') {
            return false;
        }

        $held = self::heldNodeIds($user);

        if ($held === [] || ! self::isBelow($target, $held)) {
            return false;
        }

        return self::covers(OrgAccess::grants($user), $target);
    }

    /**
     * @return array<int, array{node: OrgNode, attached_to: OrgNode|null, superiors: array<int, array{node: OrgNode, holders: array<int, array<string, mixed>>}>, team: array<int, array{node: OrgNode, depth: int, holders: array<int, array<string, mixed>>, manageable: bool}>, team_truncated: bool}>
     */
    public static function overview(User $user): array
    {
        $heldIds = self::heldNodeIds($user);

        if ($heldIds === []) {
            return [];
        }

        $all = OrgNode::query()->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');
        $children = [];

        foreach ($all as $node) {
            if ($node->parent_id !== null && $node->relation_type === 'hierarchy') {
                $children[$node->parent_id][] = $node->id;
            }
        }

        $grants = OrgAccess::grants($user);
        $plans = [];
        $needed = [];

        foreach ($heldIds as $heldId) {
            $held = $all[$heldId] ?? null;

            if ($held === null) {
                continue;
            }

            $superiors = [];
            $attachedTo = null;

            if ($held->parent_id !== null && $held->relation_type !== 'hierarchy') {
                $attachedTo = $all[$held->parent_id] ?? null;

                if ($attachedTo !== null && $attachedTo->kind !== 'position') {
                    $attachedTo = null;
                }
            }

            $current = $held;

            for ($guard = 0; $guard < 80 && $current->relation_type === 'hierarchy' && $current->parent_id !== null; $guard++) {
                $parent = $all[$current->parent_id] ?? null;

                if ($parent === null) {
                    break;
                }

                // Unités, départements et clubs ne sont pas des postes : on les traverse sans les lister.
                if ($parent->kind === 'position') {
                    $superiors[] = $parent;
                }

                $current = $parent;
            }

            $team = [];
            $truncated = false;
            self::walk($heldId, 1, $children, $all, $team, $truncated, self::insideClub($held, $all));

            // Par niveau (1 = juste sous mon poste), puis dans l'ordre de l'organigramme au sein d'un même niveau.
            usort($team, fn (array $a, array $b): int => [$a['depth'], $a['order']] <=> [$b['depth'], $b['order']]);

            $plans[] = ['node' => $held, 'attached_to' => $attachedTo, 'detached' => $held->parent_id !== null && $held->relation_type !== 'hierarchy', 'superiors' => $superiors, 'team' => $team, 'truncated' => $truncated];

            $needed[$heldId] = true;

            foreach ($superiors as $superior) {
                $needed[$superior->id] = true;
            }

            foreach ($team as $row) {
                $needed[$row['id']] = true;
            }
        }

        $holders = self::holders(array_keys($needed), $user);
        $result = [];

        foreach ($plans as $plan) {
            $result[] = [
                'node' => $plan['node'],
                'attached_to' => $plan['attached_to'],
                'detached' => $plan['detached'],
                'superiors' => array_map(fn (OrgNode $node): array => ['node' => $node, 'holders' => $holders[$node->id] ?? []], $plan['superiors']),
                'team' => array_map(function (array $row) use ($all, $holders, $grants): array {
                    $node = $all[$row['id']];

                    return [
                        'node' => $node,
                        'depth' => $row['depth'],
                        'holders' => $holders[$node->id] ?? [],
                        'manageable' => $node->kind === 'position' && self::covers($grants, $node),
                    ];
                }, $plan['team']),
                'team_truncated' => $plan['truncated'],
            ];
        }

        return $result;
    }

    /**
     * Parcours en profondeur des blocs reliés par un lien hiérarchique.
     *
     * @param array<int, array<int, int>> $children
     * @param \Illuminate\Support\Collection<int, OrgNode> $all
     * @param array<int, array{id: int, depth: int, order: int}> $rows
     */
    private static function walk(int $nodeId, int $depth, array $children, $all, array &$rows, bool &$truncated, bool $inClub): void
    {
        foreach ($children[$nodeId] ?? [] as $childId) {
            if (count($rows) >= self::MAX_TEAM_ROWS) {
                $truncated = true;

                return;
            }

            $kind = $all[$childId]->kind ?? null;

            // Les bureaux des clubs situés sous mon poste ne sont pas listés (sauf si j'occupe moi-même un poste de ce club).
            if ($kind === 'club' || ($kind === 'club_department' && ! $inClub)) {
                continue;
            }

            // Seuls les postes comptent : un bloc (unité, département) est traversé sans ajouter de niveau.
            if ($kind === 'position') {
                $rows[] = ['id' => $childId, 'depth' => $depth, 'order' => count($rows)];
                self::walk($childId, $depth + 1, $children, $all, $rows, $truncated, $inClub);
            } else {
                self::walk($childId, $depth, $children, $all, $rows, $truncated, $inClub);
            }
        }
    }

    /**
     * Le poste fait-il partie d'un club (un de ses blocs parents est un club ou un département de club) ?
     *
     * @param \Illuminate\Support\Collection<int, OrgNode> $all
     */
    private static function insideClub(OrgNode $node, $all): bool
    {
        $current = $node;

        for ($guard = 0; $guard < 80 && $current->parent_id !== null; $guard++) {
            $current = $all[$current->parent_id] ?? null;

            if ($current === null) {
                return false;
            }

            if (in_array($current->kind, ['club', 'club_department'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, int> $held
     */
    private static function isBelow(OrgNode $target, array $held): bool
    {
        $parents = OrgNode::query()->get(['id', 'parent_id', 'relation_type'])->keyBy('id');
        $current = $target;

        for ($guard = 0; $guard < 80; $guard++) {
            if ($current->relation_type !== 'hierarchy' || $current->parent_id === null) {
                return false;
            }

            $current = $parents[$current->parent_id] ?? null;

            if ($current === null) {
                return false;
            }

            if (in_array((int) $current->id, $held, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Le poste n'accorde que des droits (permission et périmètre) que ces droits couvrent déjà.
     *
     * @param array<string, array<int, string|null>> $grants
     */
    private static function covers(array $grants, OrgNode $node): bool
    {
        foreach (OrgAccess::nodeGrants($node) as $permission => $scopes) {
            $mine = $grants[$permission] ?? null;

            if ($mine === null) {
                return false;
            }

            if (in_array(null, $mine, true)) {
                continue;
            }

            foreach ($scopes as $scope) {
                if ($scope === null) {
                    return false;
                }

                $covered = false;

                foreach ($mine as $granted) {
                    if (is_string($granted) && OrgScopes::covers($granted, $scope)) {
                        $covered = true;
                        break;
                    }
                }

                if (! $covered) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Titulaires actuels par bloc.
     *
     * @param array<int, int> $nodeIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function holders(array $nodeIds, User $viewer): array
    {
        $assignments = OrgAssignment::query()
            ->whereIn('node_id', $nodeIds)
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->with('person.user.profile')
            ->orderBy('id')
            ->get();

        $holders = [];

        foreach ($assignments as $assignment) {
            $person = $assignment->person;

            if ($person === null) {
                continue;
            }

            $holders[$assignment->node_id][] = [
                'person_id' => $person->id,
                'name' => $person->displayName(),
                'job_title' => $person->job_title,
                'email' => $person->contact_visible ? $person->displayEmail() : null,
                'photo_url' => $person->photoUrl(),
                'verified' => $person->user?->profile?->identity_verified_at !== null,
                'is_me' => $person->user_id !== null && (int) $person->user_id === (int) $viewer->id,
            ];
        }

        return $holders;
    }
}
