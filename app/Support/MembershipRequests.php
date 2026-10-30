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

    public const MOTIVATION_MIN = 50;

    public const MOTIVATION_MAX = 3000;

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
     * Éléments du CV (Profil) encore à remplir avant de pouvoir demander une adhésion.
     *
     * @return list<string>
     */
    public static function missingCv(User $user): array
    {
        $settings = $user->profile?->privacy_settings;
        $settings = is_array($settings) ? $settings : [];
        $filled = fn (string $key): bool => ! empty($settings[$key]) && is_array($settings[$key]);

        $missing = [];

        if (! $filled('educations')) {
            $missing[] = 'au moins une formation';
        }

        if (! $filled('professional_experiences') && ! $filled('associative_experiences')) {
            $missing[] = 'au moins une expérience (professionnelle ou associative)';
        }

        if (! $filled('skill_keywords')) {
            $missing[] = 'vos compétences';
        }

        return $missing;
    }

    /**
     * Retire une demande encore en attente : la demande et son approbation sont supprimées (trace dans le journal).
     */
    public static function withdraw(User $user, Membership $membership, ?string $ip): void
    {
        abort_unless($membership->user_id === $user->id, 404);

        if ($membership->status !== 'pending') {
            throw ValidationException::withMessages(['membership' => 'Seule une demande en attente peut être retirée.']);
        }

        $approvals = ApprovalRequest::query()
            ->where('approvable_type', Membership::class)
            ->where('approvable_id', $membership->id);

        $approvalIds = (clone $approvals)->pluck('id');

        AppNotification::query()
            ->where('type', 'membership.submitted')
            ->whereIn('user_id', (clone $approvals)->whereNotNull('assigned_reviewer_id')->pluck('assigned_reviewer_id'))
            ->get()
            ->filter(fn (AppNotification $notification) => (int) ($notification->data['membership_id'] ?? 0) === $membership->id)
            ->each->delete();

        $approvals->delete();
        $membership->delete();

        AuditLogger::record(
            actorId: $user->id,
            action: 'membership.request.withdrawn',
            targetType: Membership::class,
            targetId: $membership->id,
            metadata: ['scope' => $membership->scope, 'node_id' => $membership->node_id, 'approval_request_ids' => $approvalIds->all()],
            ip: $ip,
        );
    }

    /**
     * @throws ValidationException
     */
    public static function submit(User $user, string $scope, ?int $nodeId, string $type, ?string $startsAt, ?string $ip, ?string $motivation = null): Membership
    {
        $motivation = trim((string) $motivation);

        if (mb_strlen($motivation) < self::MOTIVATION_MIN) {
            throw ValidationException::withMessages(['motivation_letter' => 'Rédigez une lettre de motivation d’au moins '.self::MOTIVATION_MIN.' caractères.']);
        }

        if (($missing = self::missingCv($user)) !== []) {
            throw ValidationException::withMessages(['cv' => 'Complétez votre CV (Profil) avant de demander une adhésion : '.implode(', ', $missing).'.']);
        }

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
            'motivation_letter' => $motivation,
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
