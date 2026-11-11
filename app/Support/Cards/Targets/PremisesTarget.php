<?php

namespace App\Support\Cards\Targets;

use App\Models\Premises;
use App\Models\User;
use App\Support\Cards\CheckpointTarget;
use App\Support\MembershipResponsibility;
use Illuminate\Database\Eloquent\Builder;

/** Locaux (siège, salles, espaces partagés) : l'opérateur pointe ceux de son périmètre (ou de son club). */
final class PremisesTarget implements CheckpointTarget
{
    public function key(): string
    {
        return 'premises';
    }

    public function label(): string
    {
        return 'Local';
    }

    public function options(User $operator): array
    {
        return $this->reachable($operator)->orderBy('name')->limit(100)->pluck('name', 'id')->all();
    }

    public function find(User $operator, int $id): ?array
    {
        $premises = $this->reachable($operator)->find($id);

        return $premises === null ? null : ['label' => $premises->name, 'scope' => $premises->scope, 'node_id' => $premises->node_id];
    }

    public function note(int $id, User $member): ?string
    {
        return null;
    }

    /** @return Builder<Premises> */
    private function reachable(User $operator): Builder
    {
        return MembershipResponsibility::of($operator, 'event.manage')->apply(Premises::query()->where('is_active', true), 'scope', 'node_id');
    }
}
