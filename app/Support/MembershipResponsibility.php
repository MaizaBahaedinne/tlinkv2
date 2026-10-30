<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ce qu'un responsable voit et traite : toute la plateforme (droits globaux), les périmètres entiers
 * qu'il pilote, ou seulement le club dans lequel se trouve son poste.
 */
final class MembershipResponsibility
{
    /**
     * @param array{all: bool, scopes: array<int, string>, clubs: array<int, int>} $data
     */
    private function __construct(private readonly array $data)
    {
    }

    public static function of(User $user, string $permission = 'membership.manage'): self
    {
        return new self(OrgAccess::responsibility($user, $permission));
    }

    public function all(): bool
    {
        return $this->data['all'];
    }

    /**
     * Restreint une requête (adhésions, participations…) à ce qui est sous la responsabilité de l'utilisateur.
     *
     * @template T of Builder
     * @param T $query
     * @return T
     */
    public function apply(Builder $query, string $scopeColumn = 'scope', string $nodeColumn = 'node_id'): Builder
    {
        if ($this->data['all']) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($scopeColumn, $nodeColumn): void {
            $inner->whereRaw('1 = 0');

            if ($this->data['scopes'] !== []) {
                $inner->orWhereIn($scopeColumn, $this->data['scopes']);
            }

            if ($this->data['clubs'] !== []) {
                $inner->orWhereIn($nodeColumn, $this->data['clubs']);
            }
        });
    }

    public function covers(?string $scope, ?int $nodeId): bool
    {
        return $this->data['all']
            || ($scope !== null && in_array($scope, $this->data['scopes'], true))
            || ($nodeId !== null && in_array($nodeId, $this->data['clubs'], true));
    }
}
