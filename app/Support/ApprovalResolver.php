<?php

namespace App\Support;

class ApprovalResolver
{
    /**
     * Premier validateur disponible pour un perimetre : un administrateur de ce perimetre d'abord,
     * puis un administrateur global. Le demandeur n'est jamais son propre validateur.
     * Pour un club, les responsables du club passent avant (president de club d'abord).
     */
    public static function resolveMembershipReviewer(string $scope, int $requesterId, ?int $clubId = null): ?int
    {
        if ($clubId !== null) {
            $ids = OrgAccess::userIdsInClub('approval.review', $clubId, $requesterId);

            if ($ids !== []) {
                return $ids[0];
            }
        }

        foreach (['entity_admin', 'custom', 'global_admin'] as $level) {
            $ids = OrgAccess::userIdsWith('approval.review', $scope, $requesterId, $level);

            if ($ids !== []) {
                return $ids[0];
            }
        }

        return null;
    }
}
