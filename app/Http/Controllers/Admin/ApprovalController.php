<?php

namespace App\Http\Controllers\Admin;

use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Support\ApprovalDecisionException;
use App\Support\ApprovalDecisionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApprovalController extends AdminController
{
    public const STATUSES = [
        'pending' => 'En attente',
        'approved' => 'Approuvées',
        'rejected' => 'Rejetées',
        'cancelled' => 'Annulées',
    ];

    public function index(Request $request, ApprovalDecisionService $service): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(array_keys(self::STATUSES), ['all']))],
        ]);

        $status = $filters['status'] ?? 'pending';
        $base = $this->visibleRequests($request);

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        $approvals = (clone $base)
            ->with('requester:id,name,email')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $memberships = Membership::query()
            ->with(['user:id,name,email', 'node:id,title'])
            ->whereIn('id', $approvals->getCollection()->where('approvable_type', Membership::class)->pluck('approvable_id'))
            ->get()
            ->keyBy('id');

        $decidable = [];

        foreach ($approvals->getCollection() as $approval) {
            if ($approval->status !== 'pending') {
                continue;
            }

            try {
                $decidable[$approval->id] = $service->denialReason($approval, $request->user()) === null;
            } catch (ModelNotFoundException) {
                $decidable[$approval->id] = false;
            }
        }

        return view('admin.approvals.index', [
            'approvals' => $approvals,
            'memberships' => $memberships,
            'decidable' => $decidable,
            'status' => $status,
            'statuses' => self::STATUSES,
            'counts' => $counts,
        ]);
    }

    public function decide(Request $request, ApprovalRequest $approvalRequest, ApprovalDecisionService $service): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'explanation' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $service->decide($approvalRequest, $request->user(), $data['decision'], $data['explanation'] ?? null, $request->ip());
        } catch (ApprovalDecisionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', $data['decision'] === 'approved' ? 'Demande approuvée.' : 'Demande rejetée.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ApprovalRequest>
     */
    private function visibleRequests(Request $request)
    {
        $scope = $this->context($request)->scope('approval.review');
        abort_if($scope === null, 403, 'Acces refuse.');

        $query = ApprovalRequest::query();

        if ($scope === []) {
            return $query;
        }

        // Un validateur limite a certains perimetres ne voit que les adhesions de son perimetre.
        return $query
            ->where('approvable_type', Membership::class)
            ->whereIn('approvable_id', Membership::query()->whereIn('scope', $scope)->select('id'));
    }
}
