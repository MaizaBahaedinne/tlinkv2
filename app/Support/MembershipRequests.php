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
     * il est attribué par les administrateurs. Seul un poste ouvert à plusieurs personnes, sans droit, est un poste de membre.
     */
    public static function isLeaderPosition(OrgNode $node): bool
    {
        return $node->access_level !== AccessLevels::NONE || $node->capacity !== 'multiple';
    }

    /**
     * Postes disponibles dans le périmètre (un seul par intitulé pour une entité), responsabilités comprises :
     * le contrôleur les marque pour qu'elles s'affichent sans pouvoir être choisies.
     *
     * @return \Illuminate\Support\Collection<int, OrgNode>
     */
    public static function memberPositions(string $scope, ?int $clubId)
    {
        $nodes = self::positionNodes($scope, $clubId);

        return ($clubId !== null ? $nodes : $nodes->unique('title'))->values();
    }

    /**
     * Participation à un ancien mandat : acceptée directement, uniquement comme simple membre.
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

        if ($positionId !== null) {
            $position = self::positionNodes($scope, $clubId)->firstWhere('id', $positionId);

            if ($position === null) {
                throw ValidationException::withMessages(['position_id' => 'Ce poste n’existe pas dans cette partie.']);
            }

            if (self::isLeaderPosition($position)) {
                throw ValidationException::withMessages(['position_id' => 'Les postes de responsabilité sont attribués par les administrateurs : choisissez un poste de membre.']);
            }

            $title = (string) $position->title;
        }

        $duplicate = MandateParticipation::query()
            ->where('user_id', $user->id)
            ->where('mandate_id', $mandate->id)
            ->where('scope', $scope)
            ->where('node_id', $clubId)
            ->where('position_title', $title)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['past_target' => 'Cette participation est déjà enregistrée pour ce mandat.']);
        }

        $participation = MandateParticipation::query()->create([
            'user_id' => $user->id,
            'mandate_id' => $mandate->id,
            'scope' => $scope,
            'node_id' => $clubId,
            'position_title' => $title,
        ]);

        AuditLogger::record(
            actorId: $user->id,
            action: 'membership.past.declared',
            targetType: MandateParticipation::class,
            targetId: $participation->id,
            metadata: ['mandate' => $mandate->label, 'scope' => $scope, 'node_id' => $clubId, 'position' => $title],
            ip: $ip,
        );

        return $participation;
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
