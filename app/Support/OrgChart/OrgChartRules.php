<?php

namespace App\Support\OrgChart;

use App\Models\OrgNode;
use Illuminate\Validation\ValidationException;

/**
 * Regles de coherence de l'organigramme : un seul sommet, pas de cycle,
 * rattachements sans enfants, suppression protegee.
 */
class OrgChartRules
{
    /**
     * @return array<int, int|null> id => parent_id pour tous les blocs
     */
    public function parentMap(): array
    {
        return OrgNode::query()->pluck('parent_id', 'id')->all();
    }

    /**
     * Identifiants de tous les descendants d'un bloc (le bloc lui-meme exclu).
     *
     * @return array<int, int>
     */
    public function descendantIds(int $nodeId): array
    {
        $byParent = [];

        foreach ($this->parentMap() as $id => $parentId) {
            if ($parentId !== null) {
                $byParent[$parentId][] = $id;
            }
        }

        $result = [];
        $queue = [$nodeId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($byParent[$current] ?? [] as $childId) {
                $result[] = $childId;
                $queue[] = $childId;
            }
        }

        return $result;
    }

    /**
     * Verifie qu'un bloc peut etre place sous `$parentId` avec ce type de relation.
     *
     * @throws ValidationException
     */
    public function assertValidPlacement(?OrgNode $node, ?int $parentId, string $relation): void
    {
        if ($parentId === null) {
            $root = OrgNode::query()->whereNull('parent_id')->value('id');

            if ($root !== null && (! $node || (int) $root !== (int) $node->id)) {
                $this->fail('parent_id', 'L’organigramme n’a qu’un seul sommet : choisissez un responsable.');
            }

            if ($relation !== 'hierarchy') {
                $this->fail('relation_type', 'Le sommet de l’organigramme ne peut pas être un simple rattachement.');
            }

            return;
        }

        $parent = OrgNode::query()->find($parentId);

        if (! $parent) {
            $this->fail('parent_id', 'Le responsable choisi n’existe pas.');
        }

        if ($parent->relation_type === 'attachment') {
            $this->fail('parent_id', 'Un bloc en rattachement ne peut pas avoir de sous-blocs.');
        }

        if ($node) {
            if ((int) $node->id === $parentId) {
                $this->fail('parent_id', 'Un bloc ne peut pas dépendre de lui-même.');
            }

            if (in_array($parentId, $this->descendantIds($node->id), true)) {
                $this->fail('parent_id', 'Relation impossible : ce responsable fait partie des sous-blocs de ce poste (cycle hiérarchique).');
            }

            if ($relation === 'attachment' && OrgNode::query()->where('parent_id', $node->id)->exists()) {
                $this->fail('relation_type', 'Un bloc qui a des sous-blocs ne peut pas être un simple rattachement.');
            }
        }
    }

    /**
     * Un departement de club n'existe que dans un club ; un departement de bureau national jamais dans un club.
     *
     * @throws ValidationException
     */
    public function assertValidKind(string $kind, ?int $parentId): void
    {
        if (! in_array($kind, ['department', 'club_department'], true)) {
            return;
        }

        $inClub = $this->hasClubAncestor($parentId);

        if ($kind === 'club_department' && ! $inClub) {
            $this->fail('kind', 'Un département de club doit se trouver dans un club.');
        }

        if ($kind === 'department' && $inClub) {
            $this->fail('kind', 'Dans un club, utilisez « Département de club » : le département de bureau national est réservé aux bureaux nationaux.');
        }
    }

    /**
     * Nombre de personnes actuellement affectees a un bloc (affectations non terminees).
     */
    public function currentPeopleCount(OrgNode $node): int
    {
        $today = now()->toDateString();

        return $node->assignments()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->distinct()
            ->count('person_id');
    }

    /**
     * Verifie qu'un bloc peut accueillir `$total` personnes : seuls les postes ont des titulaires,
     * et un poste a titulaire unique n'en accepte qu'un.
     *
     * @throws ValidationException
     */
    public function assertCanHold(OrgNode $node, int $total): void
    {
        if ($total === 0) {
            return;
        }

        if ($node->kind !== 'position') {
            $this->fail('person_ids', 'Seuls les postes peuvent accueillir des personnes : ajoutez un poste dans « '.$node->title.' » puis affectez-y la personne.');
        }

        if ($node->capacity !== 'multiple' && $total > 1) {
            $this->fail('person_ids', 'Le poste « '.$node->title.' » est à titulaire unique. Retirez la personne en place ou passez le poste en « Plusieurs personnes ».');
        }
    }

    private function hasClubAncestor(?int $parentId): bool
    {
        $parents = $this->parentMap();
        $kinds = OrgNode::query()->pluck('kind', 'id')->all();

        for ($guard = 0; $parentId !== null && $guard < 80; $guard++) {
            if (($kinds[$parentId] ?? null) === 'club') {
                return true;
            }

            $parentId = $parents[$parentId] ?? null;
        }

        return false;
    }

    /**
     * Retourne les identifiants a supprimer (sous-arbre), du plus profond au plus proche de la racine.
     *
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    public function deletionPlan(OrgNode $node, bool $cascade): array
    {
        if ($node->parent_id === null) {
            $this->fail('node', 'Le sommet de l’organigramme ne peut pas être supprimé.');
        }

        $descendants = $this->descendantIds($node->id);

        if ($descendants !== [] && ! $cascade) {
            $this->fail('node', 'Ce bloc contient '.count($descendants).' sous-bloc(s). Confirmez la suppression de l’ensemble ou déplacez-les d’abord.');
        }

        $parents = $this->parentMap();
        $depth = static function (int $id) use ($parents): int {
            $level = 0;

            while (($parents[$id] ?? null) !== null) {
                $id = $parents[$id];
                $level++;
            }

            return $level;
        };

        $ids = array_merge([$node->id], $descendants);
        usort($ids, static fn (int $a, int $b): int => $depth($b) <=> $depth($a));

        return $ids;
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
