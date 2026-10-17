<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Support\ApprovalResolver;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $memberships = Membership::query()
            ->with(['user:id,name,email', 'entity:id,name,slug'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 50));

        return response()->json($memberships);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id'],
            'membership_type' => ['required', 'string', 'max:60'],
            'starts_at' => ['nullable', 'date'],
        ]);

        $membership = Membership::query()->create([
            'user_id' => $request->user()->id,
            'entity_id' => $data['entity_id'],
            'department_id' => $data['department_id'] ?? null,
            'club_id' => $data['club_id'] ?? null,
            'status' => 'pending',
            'membership_type' => $data['membership_type'],
            'starts_at' => $data['starts_at'] ?? null,
        ]);

        $reviewerId = ApprovalResolver::resolveMembershipReviewer((int) $data['entity_id'], $request->user()->id);

        $approval = ApprovalRequest::query()->create([
            'request_type' => 'membership',
            'requester_id' => $request->user()->id,
            'approvable_type' => Membership::class,
            'approvable_id' => $membership->id,
            'assigned_reviewer_id' => $reviewerId,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        AuditLogger::record(
            actorId: $request->user()->id,
            action: 'membership.request.created',
            targetType: Membership::class,
            targetId: $membership->id,
            metadata: ['approval_request_id' => $approval->id, 'entity_id' => $membership->entity_id],
            ip: $request->ip(),
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

        return response()->json([
            'message' => 'Demande d\'adhesion soumise.',
            'data' => [
                'membership' => $membership,
                'approval_request' => $approval,
            ],
        ], 201);
    }
}
