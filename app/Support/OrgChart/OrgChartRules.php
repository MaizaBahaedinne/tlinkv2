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
