<?php

namespace App\Support;

use App\Models\AppNotification;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\MandateParticipation;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\OrgChart\OrgChartRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalDecisionService
{
    /**
     * Retourne la raison du refus, ou null si l'utilisateur peut decider.
     */
    public function denialReason(ApprovalRequest $approvalRequest, User $actor): ?ApprovalDecisionException
    {
        if ($approvalRequest->status !== 'pending') {
            return new ApprovalDecisionException('Cette demande est deja traitee.', 409);
        }

        if ((int) $approvalRequest->requester_id === (int) $actor->id) {
            return new ApprovalDecisionException('Auto-approbation interdite.', 403);
        }

        if ($approvalRequest->assigned_reviewer_id !== null && (int) $approvalRequest->assigned_reviewer_id !== (int) $actor->id) {
            return new ApprovalDecisionException('Tu n\'es pas le validateur assigne.', 403);
        }

        $responsibility = null;

        if ($approvalRequest->approvable_type === Membership::class) {
            $membership = Membership::query()->findOrFail($approvalRequest->approvable_id);
            $responsibility = MembershipResponsibility::of($actor, 'approval.review');

            if (! AccessScope::can($actor, 'approval.review', $membership->scope) || ! $responsibility->covers($membership->scope, $membership->node_id)) {
                return new ApprovalDecisionException('Permission insuffisante.', 403);
            }

            if ($membership->position_id !== null) {
                $position = OrgNode::query()->find($membership->position_id);

                if ($position !== null && MembershipRequests::grantsRights($position) && ! OrgAccess::responsibility($actor, 'approval.review')['all']) {
                    return new ApprovalDecisionException('Ce poste accorde des droits : seul un administrateur global peut l\'attribuer.', 403);
                }
            }
        }

        if ($approvalRequest->approvable_type === MandateParticipation::class) {
            $participation = MandateParticipation::query()->findOrFail($approvalRequest->approvable_id);
            $responsibility ??= MembershipResponsibility::of($actor, 'approval.review');

            if (! AccessScope::can($actor, 'approval.review', $participation->scope) || ! $responsibility->covers($participation->scope, $participation->node_id)) {
                return new ApprovalDecisionException('Permission insuffisante.', 403);
            }
        }

        return null;
    }

    /**
     * @throws ApprovalDecisionException
     */
    public function decide(ApprovalRequest $approvalRequest, User $actor, string $decision, ?string $explanation, ?string $ip): void
    {
        if ($denial = $this->denialReason($approvalRequest, $actor)) {
            throw $denial;
        }

        $membership = $approvalRequest->approvable_type === Membership::class
            ? Membership::query()->findOrFail($approvalRequest->approvable_id)
            : null;

        $participation = $approvalRequest->approvable_type === MandateParticipation::class
            ? MandateParticipation::query()->findOrFail($approvalRequest->approvable_id)
            : null;

        DB::transaction(function () use ($approvalRequest, $actor, $decision, $explanation, $ip, $membership, $participation): void {
            ApprovalDecision::query()->create([
                'approval_request_id' => $approvalRequest->id,
                'decided_by' => $actor->id,
                'decision' => $decision,
                'explanation' => $explanation,
            ]);

            $approvalRequest->status = $decision;
            $approvalRequest->decided_at = now();
            $approvalRequest->save();

            if ($membership !== null) {
                if ($decision === 'approved' && $membership->position_id !== null) {
                    $this->assignPosition($membership);
                    $membership->membership_type = PositionMemberships::roleOf(OrgNode::query()->find($membership->position_id));
                }

                $membership->status = $decision === 'approved' ? 'approved' : 'rejected';
                $membership->approved_by = $actor->id;
                $membership->approved_at = now();
                $membership->rejection_reason = $decision === 'rejected' ? $explanation : null;
                $membership->save();
            }

            if ($participation !== null) {
                $participation->status = $decision === 'approved' ? 'approved' : 'rejected';
                $participation->approved_by = $actor->id;
                $participation->approved_at = now();
                $participation->rejection_reason = $decision === 'rejected' ? $explanation : null;
                $participation->save();
            }

            AuditLogger::record(
                actorId: $actor->id,
                action: 'approval.decision.recorded',
                targetType: ApprovalRequest::class,
                targetId: $approvalRequest->id,
                metadata: ['decision' => $decision],
                ip: $ip,
            );

            AppNotification::query()->create([
                'user_id' => $approvalRequest->requester_id,
                'type' => 'approval.decided',
                'title' => 'Decision sur votre demande',
                'body' => $decision === 'approved'
                    ? 'Votre demande a ete approuvee.'
                    : 'Votre demande a ete rejetee.',
                'data' => ['approval_request_id' => $approvalRequest->id, 'decision' => $decision],
            ]);
        });
    }

    /**
     * Candidature approuvée : le membre devient titulaire du poste (le poste doit pouvoir l'accueillir).
     *
     * @throws ApprovalDecisionException
     */
    private function assignPosition(Membership $membership): void
    {
        $node = OrgNode::query()->find($membership->position_id);
        $user = User::query()->find($membership->user_id);

        if ($node === null || $user === null) {
            throw new ApprovalDecisionException('Le poste ou le compte du candidat n\'existe plus.', 422);
        }

        $already = OrgAssignment::query()->where('node_id', $node->id)
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))->exists();

        if ($already) {
            return;
        }

        $rules = new OrgChartRules();

        try {
            $rules->assertCanHold($node, $rules->currentPeopleCount($node) + 1);
        } catch (ValidationException) {
            throw new ApprovalDecisionException('Le poste « '.$node->title.' » est déjà pourvu : refusez la candidature ou libérez le poste.', 422);
        }

        $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], [
            'full_name' => $user->name,
            'contact_visible' => true,
        ]);

        OrgAssignment::query()->firstOrCreate(['node_id' => $node->id, 'person_id' => $person->id]);
    }
}
