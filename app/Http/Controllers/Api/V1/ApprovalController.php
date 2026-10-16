<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Support\AccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $approvals = ApprovalRequest::query()
            ->with(['requester:id,name,email'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 50));

        return response()->json($approvals);
    }

    public function decide(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'explanation' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($approvalRequest->status !== 'pending') {
            return response()->json(['message' => 'Cette demande est deja traitee.'], 409);
        }

        if ((int) $approvalRequest->requester_id === (int) $request->user()->id) {
            return response()->json(['message' => 'Auto-approbation interdite.'], 403);
        }

        if ($approvalRequest->assigned_reviewer_id !== null && (int) $approvalRequest->assigned_reviewer_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Tu n\'es pas le validateur assigne.'], 403);
        }

        $membership = null;
        if ($approvalRequest->approvable_type === Membership::class) {
            $membership = Membership::query()->findOrFail($approvalRequest->approvable_id);

            if (! AccessScope::can($request->user(), 'approval.review', $membership->entity_id)) {
                return response()->json(['message' => 'Permission insuffisante.'], 403);
            }
        }

        DB::transaction(function () use ($request, $approvalRequest, $data, $membership): void {
            ApprovalDecision::query()->create([
                'approval_request_id' => $approvalRequest->id,
                'decided_by' => $request->user()->id,
                'decision' => $data['decision'],
                'explanation' => $data['explanation'] ?? null,
            ]);

            $approvalRequest->status = $data['decision'];
            $approvalRequest->decided_at = now();
            $approvalRequest->save();

            if ($membership !== null) {
                $membership->status = $data['decision'] === 'approved' ? 'approved' : 'rejected';
                $membership->approved_by = $request->user()->id;
                $membership->approved_at = now();
                $membership->rejection_reason = $data['decision'] === 'rejected' ? ($data['explanation'] ?? null) : null;
                $membership->save();
            }
        });

        return response()->json([
            'message' => 'Decision enregistree.',
        ]);
    }
}
