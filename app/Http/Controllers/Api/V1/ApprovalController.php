<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Support\ApprovalDecisionException;
use App\Support\ApprovalDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        try {
            app(ApprovalDecisionService::class)->decide(
                $approvalRequest,
                $request->user(),
                $data['decision'],
                $data['explanation'] ?? null,
                $request->ip(),
            );
        } catch (ApprovalDecisionException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }

        return response()->json([
            'message' => 'Decision enregistree.',
        ]);
    }
}
