<?php

namespace App\Support\OrgChart;

use App\Models\Club;
use App\Models\Department;
use App\Models\Entity;
use App\Models\Governorate;
use App\Models\OrgNode;
use Illuminate\Support\Str;

/**
 * Relie l'organigramme a la structure de la plateforme (entites, departements, institutions, clubs).
 *
 * - Les blocs « club » suivent leur club (entite, departement, institution, gouvernorat) ;
 *   le club reste la source de verite.
 * - Les blocs systeme (presidence, bureaux nationaux, alumni) portent un lien explicite
 *   que leurs sous-blocs heritent.
 * - Tout club d'une universite ou d'un lycee a son bloc, range par gouvernorat.
 *
 * Toutes les operations sont idempotentes.
 */
class OrgChartLinker
{
    /** Blocs systeme => [entite, departement]. */
    public const SYSTEM_LINKS = [
        'presidency' => ['foundation', 'bureau-national'],
        'university-office' => ['university', 'bureau-universitaire'],
        'highschool-office' => ['high-school', 'bureau-lycee'],
        'alumni' => ['alumni', 'bureau-alumni'],
    ];

    /** Departement par defaut de chaque entite : slug => nom. */
    private const DEPARTMENTS = [
        'foundation' => ['bureau-national', 'Bureau national'],
        'university' => ['bureau-universitaire', 'Bureau universitaire'],
        'high-school' => ['bureau-lycee', 'Bureau lycée'],
        'alumni' => ['bureau-alumni', 'Bureau alumni'],
    ];

    /** Entite du club => [branche de l'organigramme, categorie]. */
    private const BRANCHES = [
        'university' => ['university-clubs', 'university_club'],
        'high-school' => ['highschool-clubs', 'highschool_club'],
    ];

    private static int $suspended = 0;

    /**
     * Execute un traitement sans synchronisation automatique par l'observateur de clubs.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function withoutSync(callable $callback): mixed
    {
        self::$suspended++;

        try {
            return $callback();
        } finally {
            self::$suspended--;
        }
    }

    public function isActive(): bool
    {
        return self::$suspended === 0 && OrgNode::query()->exists();
    }

    /**
     * Rattrapage complet : liens des blocs systeme, blocs manquants des clubs, liens des blocs existants.
     *
     * @return array{linked: int, created: int, updated: int, skipped: int}
     */
    public function sync(): array
    {
        return self::withoutSync(function (): array {
            $linked = $this->linkSystemNodes();
            $created = 0;
            $updated = 0;
            $skipped = 0;

            $nodes = OrgNode::query()->whereNotNull('club_id')->get()->keyBy('club_id');

            $clubs = Club::query()->with(['governorate', 'entity'])->get()
                ->sortBy(fn (Club $club): string => Str::lower(($club->governorate?->name ?? '~').'|'.$club->name));

            foreach ($clubs as $club) {
                if (isset($nodes[$club->id])) {
                    $updated += $this->refreshNode($nodes[$club->id], $club) ? 1 : 0;

                    continue;
                }

                if ($this->createClubNode($club) !== null) {
                    $created++;
                } else {
                    $skipped++;
                }
            }

            return ['linked' => $linked, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped];
        });
    }

    /**
     * Pose l'entite et le departement des blocs systeme, sans ecraser un lien deja choisi.
     */
    public function linkSystemNodes(): int
    {
        $count = 0;

        foreach (self::SYSTEM_LINKS as $key => [$entitySlug, $departmentSlug]) {
            $node = OrgNode::query()->where('system_key', $key)->first();
            $entity = Entity::query()->where('slug', $entitySlug)->first();

            if (! $node || ! $entity) {
                continue;
            }

            $department = $this->department($entity);

            if ($node->entity_id === null) {
                $node->entity_id = $entity->id;
            }

            if ($node->department_id === null && $department) {
                $node->department_id = $department->id;
            }

            if ($node->isDirty()) {
                $node->save();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Departement « bureau » d'une entite (cree s'il manque).
     */
    public function department(Entity $entity): ?Department
    {
        [$slug, $name] = self::DEPARTMENTS[$entity->slug] ?? [null, null];

        if ($slug === null) {
            return null;
        }

        return Department::query()->firstOrCreate(
            ['entity_id' => $entity->id, 'slug' => $slug],
            ['name' => $name]
        );
    }

    /**
     * Bloc d'un club, cree s'il n'existe pas, sinon mis a jour. Null si le club n'a pas de branche (entite).
     */
    public function ensureClubNode(Club $club): ?OrgNode
    {
        $node = OrgNode::query()->where('club_id', $club->id)->first();

        if ($node) {
            $this->refreshNode($node, $club);

            return $node;
        }

        return $this->createClubNode($club);
    }

    /**
     * Repercute les changements d'un club sur son bloc (nom, liens, gouvernorat, branche).
     */
    public function syncFromClub(Club $club): void
    {
        $node = OrgNode::query()->where('club_id', $club->id)->first();

        if ($node) {
            $this->refreshNode($node, $club);
        }
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
     * Branche (University Clubs / High School Clubs) d'un club, null si son entite n'en a pas.
     *
     * @return array{0: OrgNode, 1: string}|null
     */
    public function branchFor(Club $club): ?array
    {
        // Relu depuis la base : la relation chargee sur le modele peut etre obsolete apres un changement d'entite.
        $slug = $club->entity_id ? Entity::query()->whereKey($club->entity_id)->value('slug') : null;
        [$key, $category] = self::BRANCHES[$slug] ?? [null, null];

        if ($key === null) {
            return null;
        }

        $branch = OrgNode::query()->where('system_key', $key)->first();

        return $branch ? [$branch, $category] : null;
    }

    private function createClubNode(Club $club): ?OrgNode
    {
        $resolved = $this->branchFor($club);

        if ($resolved === null) {
            return null;
        }

        [$branch, $category] = $resolved;
        $governorate = $club->governorate_id ? Governorate::query()->find($club->governorate_id) : null;
        $parent = $this->groupFor($branch, $governorate);

        return OrgNode::query()->create([
            'parent_id' => $parent->id,
            'relation_type' => 'hierarchy',
            'kind' => 'club',
            'category' => $category,
            'title' => $club->name,
            'subtitle' => $governorate?->name,
            'club_id' => $club->id,
            'governorate_id' => $club->governorate_id,
            'entity_id' => $club->entity_id,
            'department_id' => $club->department_id,
            'institution_id' => $club->institution_id,
            'is_collapsed' => true,
            'sort_order' => $this->nextSort($parent->id),
        ]);
    }

    /**
     * Aligne un bloc sur son club. Retourne true si quelque chose a change.
     */
    private function refreshNode(OrgNode $node, Club $club): bool
    {
        $changed = false;

        $governorateChanged = $node->governorate_id !== $club->governorate_id;

        $node->fill([
            'title' => $club->name,
            'governorate_id' => $club->governorate_id,
            'entity_id' => $club->entity_id,
            'department_id' => $club->department_id,
            'institution_id' => $club->institution_id,
        ]);

        if ($governorateChanged) {
            $node->subtitle = $club->governorate_id ? Governorate::query()->whereKey($club->governorate_id)->value('name') : null;
        }

        // Un club range dans un groupe de gouvernorat suit son gouvernorat et sa branche.
        $parent = $node->parent_id ? OrgNode::query()->find($node->parent_id) : null;

        if ($parent && $parent->kind === 'unit' && Str::contains((string) $parent->system_key, '-clubs:')) {
            $resolved = $this->branchFor($club);

            if ($resolved !== null) {
                $governorate = $club->governorate_id ? Governorate::query()->find($club->governorate_id) : null;
                $expected = $this->groupFor($resolved[0], $governorate);

                if ($expected->id !== $parent->id) {
                    $node->parent_id = $expected->id;
                    $node->category = $resolved[1];
                    $node->sort_order = $this->nextSort($expected->id);
                }
            }
        }

        if ($node->isDirty()) {
            $node->save();
            $changed = true;
        }

        return $changed;
    }

    private function nextSort(int $parentId): int
    {
        $max = OrgNode::query()->where('parent_id', $parentId)->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }
}
