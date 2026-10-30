<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Support\MembershipRequests;
use App\Support\OrgScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $memberships = Membership::query()
            ->with(['user:id,name,email', 'node:id,title'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 15), 50));

        return response()->json($memberships);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(OrgScopes::slugs())],
            'node_id' => ['nullable', 'integer', Rule::exists('org_nodes', 'id')->where('kind', 'club')],
            'membership_type' => ['required', 'string', 'max:60'],
            'starts_at' => ['nullable', 'date'],
        ]);

        $membership = MembershipRequests::submit(
            $request->user(),
            $data['scope'],
            isset($data['node_id']) ? (int) $data['node_id'] : null,
            $data['membership_type'],
            $data['starts_at'] ?? null,
            $request->ip(),
        );

        $approval = ApprovalRequest::query()
            ->where('approvable_type', Membership::class)
            ->where('approvable_id', $membership->id)
            ->first();

        return response()->json([
            'message' => 'Demande d\'adhesion soumise.',
            'data' => [
                'membership' => $membership,
                'approval_request' => $approval,
            ],
        ], 201);
    }
}
