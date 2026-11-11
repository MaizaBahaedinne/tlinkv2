<?php

namespace App\Support\Cards;

use App\Models\User;
use App\Support\Cards\Targets\ActivityTarget;
use App\Support\Cards\Targets\EventTarget;
use App\Support\Cards\Targets\PremisesTarget;

/** Registre des cibles de pointage : un nouveau module s'y ajoute en une ligne. */
final class Checkpoints
{
    /** @return array<string, CheckpointTarget> */
    public static function targets(): array
    {
        $targets = [new ActivityTarget(), new EventTarget(), new PremisesTarget()];

        return collect($targets)->keyBy(fn (CheckpointTarget $target): string => $target->key())->all();
    }

    /** @return array<string, string> clé => libellé */
    public static function labels(): array
    {
        return array_map(fn (CheckpointTarget $target): string => $target->label(), self::targets());
    }

    /**
     * Choix proposés à l'opérateur, regroupés par type.
     *
     * @return array<string, array{label: string, items: array<int, string>}>
     */
    public static function options(User $operator): array
    {
        $groups = [];

        foreach (self::targets() as $key => $target) {
            $groups[$key] = ['label' => $target->label(), 'items' => $target->options($operator)];
        }

        return $groups;
    }

    /**
     * @return array{label: string, scope: string|null, node_id: int|null}|null
     */
    public static function find(string $type, int $id, User $operator): ?array
    {
        return (self::targets()[$type] ?? null)?->find($operator, $id);
    }

    public static function note(string $type, int $id, User $member): ?string
    {
        return (self::targets()[$type] ?? null)?->note($id, $member);
    }
}
