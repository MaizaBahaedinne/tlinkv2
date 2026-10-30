<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Demande d'adhésion d'un membre : l'adhésion est créée « en attente » avec une demande d'approbation
 * confiée au validateur du périmètre. Partagé par l'API et par la page « Mes adhésions ».
 */
final class MembershipRequests
{
    /** Statuts qui bloquent une nouvelle demande pour la même cible. */
    private const BLOCKING = ['pending', 'approved', 'suspended'];

    /**
     * Clubs actifs ouverts aux demandes, groupés par périmètre.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, OrgNode>>
     */
    public static function clubsByScope()
    {
        return OrgNode::query()
            ->where('kind', 'club')
            ->where('status', 'active')
            ->whereNotNull('scope')
            ->orderBy('title')
            ->get(['id', 'title', 'subtitle', 'scope'])
            ->filter(fn (OrgNode $club) => OrgScopes::valid($club->scope))
            ->groupBy('scope');
    }

    /**
     * @throws ValidationException
     */
    public static function submit(User $user, string $scope, ?int $nodeId, string $type, ?string $startsAt, ?string $ip): Membership
    {
        $club = $nodeId !== null ? OrgNode::query()->where('kind', 'club')->find($nodeId) : null;

        if ($nodeId !== null && $club === null) {
            throw ValidationException::withMessages(['target' => 'Ce club est introuvable.']);
        }

        if ($club !== null && $club->scope !== null && $club->scope !== $scope) {
            throw ValidationException::withMessages(['target' => 'Ce club n’appartient pas à ce périmètre.']);
        }

        $existing = Membership::query()
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->where('node_id', $nodeId)
            ->whereIn('status', self::BLOCKING)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages(['target' => $existing->status === 'pending'
                ? 'Vous avez déjà une demande en attente pour cette adhésion.'
                : 'Vous êtes déjà adhérent(e) ici.']);
        }

        $membership = Membership::query()->create([
            'user_id' => $user->id,
            'scope' => $scope,
            'node_id' => $nodeId,
            'status' => 'pending',
            'membership_type' => $type,
            'starts_at' => $startsAt,
        ]);

        $reviewerId = ApprovalResolver::resolveMembershipReviewer($scope, $user->id);

        $approval = ApprovalRequest::query()->create([
            'request_type' => 'membership',
            'requester_id' => $user->id,
            'approvable_type' => Membership::class,
            'approvable_id' => $membership->id,
            'assigned_reviewer_id' => $reviewerId,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        AuditLogger::record(
            actorId: $user->id,
            action: 'membership.request.created',
            targetType: Membership::class,
            targetId: $membership->id,
            metadata: ['approval_request_id' => $approval->id, 'scope' => $membership->scope],
            ip: $ip,
        );

        if ($reviewerId !== null) {
            AppNotification::query()->create([
                'user_id' => $reviewerId,
                'type' => 'membership.submitted',
                'title' => 'Nouvelle demande d\'adhesion',
                'body' => 'Une demande d\'adhesion attend votre validation.',
                'data' => ['membership_id' => $membership->id, 'approval_request_id' => $approval->id],
            ]);
        }

        return $membership;
    }
}
