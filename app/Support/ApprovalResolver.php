<?php

namespace App\Support;

class ApprovalResolver
{
    /**
     * Validateur principal d'une demande (celui qui lui est assigné) : le premier de la liste des validateurs
     * de l'entité si elle en a une, sinon le premier validateur disponible (voir reviewers()).
     */
    public static function resolveMembershipReviewer(string $scope, int $requesterId, ?int $clubId = null, bool $globalOnly = false): ?int
    {
        return self::reviewers($scope, $requesterId, $clubId, $globalOnly)[0] ?? null;
    }

    /**
     * Validateurs à prévenir pour une demande. Les validateurs désignés pour l'entité (Structure > Périmètres) passent en premier
     * et sont tous prévenus ; sans liste, le premier validateur disponible : un administrateur du club, de l'entité, puis un
     * administrateur global. Le demandeur n'est jamais son propre validateur.
     * Un poste qui accorde des droits (globalOnly) reste réservé aux administrateurs globaux.
     *
     * @return array<int, int>
     */
    public static function reviewers(string $scope, int $requesterId, ?int $clubId = null, bool $globalOnly = false): array
    {
        if ($globalOnly) {
            // Poste qui accorde des droits : seul un administrateur global peut l'attribuer.
            $ids = OrgAccess::userIdsWith('approval.review', $scope, $requesterId, 'global_admin');

            return $ids === [] ? [] : [$ids[0]];
        }

        $designated = array_values(array_diff(ScopeValidators::ids($scope), [$requesterId]));

        if ($designated !== []) {
            return $designated;
        }

        if ($clubId !== null) {
            $ids = OrgAccess::userIdsInClub('approval.review', $clubId, $requesterId);

            if ($ids !== []) {
                return [$ids[0]];
            }
        }

        foreach (['entity_admin', 'custom', 'global_admin'] as $level) {
            $ids = OrgAccess::userIdsWith('approval.review', $scope, $requesterId, $level);

            if ($ids !== []) {
                return [$ids[0]];
            }
        }

        return [];
    }
}
