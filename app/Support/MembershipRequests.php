<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\ApprovalRequest;
use App\Models\Mandate;
use App\Models\MandateParticipation;
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
     * Tous les postes du périmètre choisi : le club (ses départements compris) ou l'entité.
     *
     * @return \Illuminate\Support\Collection<int, OrgNode>
     */
    private static function positionNodes(string $scope, ?int $clubId)
    {
        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $scopes = OrgAccess::scopeMap();

        $inside = static function (int $id) use ($clubId, $parents): bool {
            for ($guard = 0; $id !== null && $guard < 80; $guard++) {
                if ($id === $clubId) {
                    return true;
                }

                $id = isset($parents[$id]) ? (int) $parents[$id] : null;
            }

            return false;
        };

        return OrgNode::query()
            ->where('kind', 'position')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'title', 'subtitle', 'access_level', 'capacity'])
            ->filter(fn (OrgNode $node) => $clubId !== null ? $inside((int) $node->id) : ($scopes[$node->id] ?? null) === $scope)
            ->values();
    }

    /**
     * Un poste de responsabilité (président, VP, assistant…) porte des droits ou n'a qu'un titulaire :
     * le déclarer pour un ancien mandat exige la validation d'un administrateur.
     */
    public static function isLeaderPosition(OrgNode $node): bool
    {
        return $node->access_level !== AccessLevels::NONE || $node->capacity !== 'multiple';
    }

    /**
     * Postes disponibles dans le périmètre (un seul par intitulé pour une entité), responsabilités comprises :
     * le contrôleur les marque pour que la liste indique qu'ils sont soumis à validation.
     *
     * @return \Illuminate\Support\Collection<int, OrgNode>
     */
    public static function memberPositions(string $scope, ?int $clubId)
    {
        $nodes = self::positionNodes($scope, $clubId);

        return ($clubId !== null ? $nodes : $nodes->unique('title'))->values();
    }

    /**
     * Participation à un ancien mandat. Simple membre : acceptée directement. Poste de responsabilité :
     * en attente, avec une demande d'approbation confiée au validateur du périmètre.
     *
     * @throws ValidationException
     */
    public static function declarePast(User $user, Mandate $mandate, string $scope, ?int $clubId, ?int $positionId, ?string $ip): MandateParticipation
    {
        if ($mandate->status !== Mandate::STATUS_CLOSED) {
            throw ValidationException::withMessages(['past_target' => 'Seuls les mandats précédents peuvent être déclarés ainsi : pour le mandat en cours, envoyez une demande d’adhésion.']);
        }

        $club = $clubId !== null ? OrgNode::query()->where('kind', 'club')->find($clubId) : null;

        if ($clubId !== null && ($club === null || ($club->scope !== null && $club->scope !== $scope))) {
            throw ValidationException::withMessages(['past_target' => 'Ce club est introuvable dans ce périmètre.']);
        }

        $title = null;
        $leadership = false;

        if ($positionId !== null) {
            $position = self::positionNodes($scope, $clubId)->firstWhere('id', $positionId);

            if ($position === null) {
                throw ValidationException::withMessages(['position_id' => 'Ce poste n’existe pas dans cette partie.']);
            }

            $title = (string) $position->title;
            $leadership = self::isLeaderPosition($position);
        }

        $duplicate = MandateParticipation::query()
            ->where('user_id', $user->id)
            ->where('mandate_id', $mandate->id)
            ->where('scope', $scope)
            ->where('node_id', $clubId)
            ->where('position_title', $title)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['past_target' => 'Cette participation est déjà enregistrée (ou en attente) pour ce mandat.']);
        }

        $participation = MandateParticipation::query()->create([
            'user_id' => $user->id,
            'mandate_id' => $mandate->id,
            'scope' => $scope,
            'node_id' => $clubId,
            'position_title' => $title,
            'status' => $leadership ? 'pending' : 'approved',
            'approved_at' => $leadership ? null : now(),
        ]);

        $approvalId = null;

        if ($leadership) {
            $reviewerId = ApprovalResolver::resolveMembershipReviewer($scope, $user->id, $clubId);

            $approval = ApprovalRequest::query()->create([
                'request_type' => 'mandate_leadership',
                'requester_id' => $user->id,
                'approvable_type' => MandateParticipation::class,
                'approvable_id' => $participation->id,
                'assigned_reviewer_id' => $reviewerId,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
            $approvalId = $approval->id;

            if ($reviewerId !== null) {
                AppNotification::query()->create([
                    'user_id' => $reviewerId,
                    'type' => 'membership.leadership_submitted',
                    'title' => 'Poste de responsabilité à valider',
                    'body' => $user->name.' déclare le poste « '.$title.' » sur le mandat '.$mandate->label.'.',
                    'data' => ['participation_id' => $participation->id, 'approval_request_id' => $approval->id],
                ]);
            }
        }

        AuditLogger::record(
            actorId: $user->id,
            action: 'membership.past.declared',
            targetType: MandateParticipation::class,
            targetId: $participation->id,
            metadata: ['mandate' => $mandate->label, 'scope' => $scope, 'node_id' => $clubId, 'position' => $title, 'leadership' => $leadership, 'approval_request_id' => $approvalId],
            ip: $ip,
        );

        return $participation;
    }

    /**
     * Retire une participation : une demande en attente (ou refusée) et une participation de membre se retirent ;
     * un poste de responsabilité déjà validé par un administrateur ne se retire plus ici.
     */
    public static function withdrawPast(User $user, MandateParticipation $participation, ?string $ip): void
    {
        abort_unless($participation->user_id === $user->id, 404);

        $approvals = ApprovalRequest::query()
            ->where('approvable_type', MandateParticipation::class)
            ->where('approvable_id', $participation->id);

        if ($participation->status === 'approved' && (clone $approvals)->exists()) {
            throw ValidationException::withMessages(['participation' => 'Ce poste de responsabilité a été validé par un administrateur : contactez-les pour le modifier.']);
        }

        $reviewerIds = (clone $approvals)->whereNotNull('assigned_reviewer_id')->pluck('assigned_reviewer_id');

        AppNotification::query()
            ->where('type', 'membership.leadership_submitted')
            ->whereIn('user_id', $reviewerIds)
            ->get()
            ->filter(fn (AppNotification $notification) => (int) ($notification->data['participation_id'] ?? 0) === $participation->id)
            ->each->delete();

        $approvals->delete();
        $participation->delete();

        AuditLogger::record(
            actorId: $user->id,
            action: 'membership.past.withdrawn',
            targetType: MandateParticipation::class,
            targetId: $participation->id,
            metadata: ['mandate_id' => $participation->mandate_id, 'scope' => $participation->scope, 'position' => $participation->position_title],
            ip: $ip,
        );
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

        $reviewerId = ApprovalResolver::resolveMembershipReviewer($scope, $user->id, $nodeId);

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
