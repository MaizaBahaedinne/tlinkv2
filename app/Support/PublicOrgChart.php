<?php

namespace App\Support;

use App\Models\OrgNode;
use Illuminate\Support\Collection;

/**
 * Lecture publique de l'organigramme : les postes occupes, ranges par perimetre
 * (Fondation, University, High School, Alumni) et dans l'ordre de l'arbre.
 * Le perimetre d'un bloc est le sien, sinon celui du plus proche parent (voir OrgAccess::scopeMap()).
 */
class PublicOrgChart
{
    /** Les clubs ont leur propre page : leurs equipes ne sont pas presentees dans « Equipe ». */
    private const CLUB_CATEGORIES = ['regional_club', 'university_club', 'highschool_club'];

    /** Poste technique : ses titulaires administrent la plateforme et ne sont pas presentes au public. */
    private const HIDDEN_SYSTEM_KEYS = ['national-administrators'];

    /**
     * @return array<string, Collection<int, OrgNode>> perimetre => postes occupes, dans l'ordre de l'organigramme
     */
    public static function teamByScope(): array
    {
        $today = now()->toDateString();
        $scopes = OrgAccess::scopeMap();
        $rank = self::treeRank();

        $nodes = OrgNode::query()
            ->where('kind', 'position')
            ->whereNotIn('category', self::CLUB_CATEGORIES)
            ->where(fn ($query) => $query->whereNull('system_key')->orWhereNotIn('system_key', self::HIDDEN_SYSTEM_KEYS))
            ->with([
                'assignments' => fn ($query) => $query
                    ->where(fn ($inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
                    ->where(fn ($inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>=', $today)),
                'assignments.person.user.profile',
            ])
            ->get()
            ->filter(fn (OrgNode $node): bool => $node->assignments->contains(fn ($assignment): bool => $assignment->person !== null))
            ->sortBy(fn (OrgNode $node): int => $rank[$node->id] ?? PHP_INT_MAX)
            ->groupBy(fn (OrgNode $node): string => $scopes[$node->id] ?? 'foundation');

        $groups = [];

        foreach (array_keys(OrgScopes::all()) as $scope) {
            if ($nodes->has($scope)) {
                $groups[$scope] = $nodes->get($scope)->values();
            }
        }

        return $groups;
    }

    /**
     * Titulaire du poste de président (organigramme) : nom, titre du poste et photo, pour la page « À propos ».
     *
     * @return array{name: string, title: string, photo: string|null}|null
     */
    public static function president(): ?array
    {
        $today = now()->toDateString();

        $node = OrgNode::query()
            ->where('system_key', 'presidency')
            ->where('kind', 'position')
            ->with([
                'assignments' => fn ($query) => $query
                    ->where(fn ($inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $today))
                    ->where(fn ($inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
                    ->orderBy('id'),
                'assignments.person.user.profile',
            ])
            ->first();

        $person = $node?->assignments->first(fn ($assignment): bool => $assignment->person !== null)?->person;

        if ($node === null || $person === null) {
            return null;
        }

        return ['name' => $person->displayName(), 'title' => $node->title, 'photo' => $person->photoUrl()];
    }

    /**
     * Rang de chaque bloc dans un parcours en profondeur de l'arbre (freres tries par `sort_order`, puis `id`).
     *
     * @return array<int, int> id => rang
     */
    private static function treeRank(): array
    {
        $children = [];

        foreach (OrgNode::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id']) as $node) {
            $children[(int) ($node->parent_id ?? 0)][] = (int) $node->id;
        }

        $rank = [];
        $stack = array_reverse($children[0] ?? []);

        while ($stack !== []) {
            $id = array_pop($stack);

            if (isset($rank[$id])) {
                continue; // garde-fou contre un cycle
            }

            $rank[$id] = count($rank);

            foreach (array_reverse($children[$id] ?? []) as $childId) {
                $stack[] = $childId;
            }
        }

        return $rank;
    }
}
