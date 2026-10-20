<?php

namespace App\Support;

class ApprovalResolver
{
    /**
     * Premier validateur disponible pour une entite : un administrateur de cette entite d'abord,
     * puis un administrateur global. Le demandeur n'est jamais son propre validateur.
     */
    public static function resolveMembershipReviewer(int $entityId, int $requesterId): ?int
    {
        foreach (['entity_admin', 'global_admin'] as $level) {
            $ids = OrgAccess::userIdsWith('approval.review', $entityId, $requesterId, $level);

            if ($ids !== []) {
                return $ids[0];
            }
        }

        return null;
    }
}
