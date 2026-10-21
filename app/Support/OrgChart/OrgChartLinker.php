<?php

namespace App\Support\OrgChart;

use App\Models\Governorate;
use App\Models\OrgNode;
use App\Support\OrgScopes;
use Illuminate\Support\Str;

/**
 * Garde l'organigramme coherent avec les perimetres de la plateforme.
 *
 * - Les blocs systeme (presidence, bureaux nationaux, alumni) portent un perimetre explicite
 *   que leurs sous-blocs heritent.
 * - Un club (bloc de type « club ») d'une universite ou d'un lycee est range dans sa branche,
 *   par gouvernorat, selon son perimetre.
 *
 * Toutes les operations sont idempotentes.
 */
class OrgChartLinker
{
    /** Blocs systeme => perimetre. */
    public const SYSTEM_SCOPES = [
        'presidency' => 'foundation',
        'university-office' => 'university',
        'highschool-office' => 'high-school',
        'alumni' => 'alumni',
    ];

    /**
     * Rattrapage complet : perimetre des blocs systeme et rangement des clubs.
     *
     * @return array{linked: int, updated: int}
     */
    public function sync(): array
    {
        $linked = $this->linkSystemNodes();
        $updated = 0;

        foreach (OrgNode::query()->where('kind', 'club')->get() as $club) {
            $updated += $this->placeClub($club) ? 1 : 0;
        }

        return ['linked' => $linked, 'updated' => $updated];
    }

    /**
     * Pose le perimetre des blocs systeme, sans ecraser un choix deja fait.
     */
    public function linkSystemNodes(): int
    {
        $count = 0;

        foreach (self::SYSTEM_SCOPES as $key => $scope) {
            $node = OrgNode::query()->where('system_key', $key)->first();

            if ($node && $node->scope === null) {
                $node->update(['scope' => $scope]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Range un club dans la branche et le groupe de gouvernorat de son perimetre.
     * Un club deja range ailleurs a la main (hors groupe de gouvernorat) n'est jamais deplace.
     * Retourne true si le bloc a change.
     */
    public function placeClub(OrgNode $club): bool
    {
        if ($club->scope === null) {
            $category = (string) $club->category;
            $scope = collect(OrgScopes::CLUB_BRANCHES)->search(fn (array $branch): bool => $branch[1] === $category);

            if ($scope !== false && $scope !== null) {
                $club->scope = $scope;
            }
        }

        $parent = $club->parent_id ? OrgNode::query()->find($club->parent_id) : null;

        if ($parent && $parent->kind === 'unit' && Str::contains((string) $parent->system_key, '-clubs:')) {
            $resolved = $this->branchFor($club->scope);

            if ($resolved !== null) {
                $governorate = $club->governorate_id ? Governorate::query()->find($club->governorate_id) : null;
                $expected = $this->groupFor($resolved[0], $governorate);

                if ($expected->id !== $parent->id) {
                    $club->parent_id = $expected->id;
                    $club->category = $resolved[1];
                    $club->sort_order = $this->nextSort($expected->id);
                }
            }
        }

        if (! $club->isDirty()) {
            return false;
        }

        $club->save();

        return true;
    }

    /**
     * Unite de regroupement par gouvernorat sous une branche (ex. University Clubs > Sfax).
     */
    public function groupFor(OrgNode $branch, ?Governorate $governorate): OrgNode
    {
        $key = $branch->system_key.':'.($governorate?->slug ?? 'sans-gouvernorat');

        $existing = OrgNode::query()->where('system_key', $key)->first();

        if ($existing) {
            return $existing;
        }

        return OrgNode::query()->create([
            'parent_id' => $branch->id,
            'relation_type' => 'hierarchy',
            'kind' => 'unit',
            'category' => $branch->category,
            'title' => $governorate?->name ?? 'Sans gouvernorat',
            'governorate_id' => $governorate?->id,
            'children_layout' => 'vertical',
            'is_collapsed' => true,
            'sort_order' => $this->nextSort($branch->id),
            'system_key' => $key,
        ]);
    }

    /**
     * Branche (University Clubs / High School Clubs) d'un perimetre, null s'il n'en a pas.
     *
     * @return array{0: OrgNode, 1: string}|null
     */
    public function branchFor(?string $scope): ?array
    {
        [$key, $category] = OrgScopes::CLUB_BRANCHES[$scope] ?? [null, null];

        if ($key === null) {
            return null;
        }

        $branch = OrgNode::query()->where('system_key', $key)->first();

        return $branch ? [$branch, $category] : null;
    }

    private function nextSort(int $parentId): int
    {
        $max = OrgNode::query()->where('parent_id', $parentId)->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }
}
