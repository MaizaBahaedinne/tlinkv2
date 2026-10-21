<?php

namespace App\Http\Controllers\Admin;

use App\Models\Membership;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MembershipController extends AdminController
{
    public const STATUSES = [
        'pending' => 'En attente',
        'approved' => 'Approuvées',
        'rejected' => 'Rejetées',
        'suspended' => 'Suspendues',
        'archived' => 'Archivées',
    ];

    /**
     * Transitions autorisees ici. Les demandes en attente passent par le module Approbations.
     */
    public const TRANSITIONS = [
        'approved' => ['suspended', 'archived'],
        'suspended' => ['approved', 'archived'],
        'archived' => ['approved'],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'scope' => ['nullable', Rule::in(OrgScopes::slugs())],
        ]);

        $base = $this->scoped(Membership::query(), $request, 'membership.manage');

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        $memberships = (clone $base)
            ->with(['user:id,name,email', 'node:id,title'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $query->whereHas('user', fn ($user) => $user->where('name', 'like', "%{$needle}%")->orWhere('email', 'like', "%{$needle}%"));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.memberships.index', [
            'memberships' => $memberships,
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'transitions' => self::TRANSITIONS,
            'counts' => $counts,
            'canReview' => $this->context($request)->can('approval.review'),
            'scopes' => $this->scopeOptions($request, 'membership.manage'),
        ]);
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeScope($request, 'membership.manage', $membership->scope);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'suspended', 'archived'])],
        ]);

        $allowed = self::TRANSITIONS[$membership->status] ?? [];

        if (! in_array($data['status'], $allowed, true)) {
            return back()->with('error', $membership->status === 'pending'
                ? 'Les demandes en attente se traitent depuis le module Approbations.'
                : 'Cette transition de statut n’est pas autorisée.');
        }

        $from = $membership->status;
        $membership->update(['status' => $data['status']]);

        $this->audit($request, 'admin.membership.status_changed', $membership, [
            'user_id' => $membership->user_id,
            'from' => $from,
            'to' => $data['status'],
        ]);

        return back()->with('status', 'Statut de l’adhésion mis à jour.');
    }
}
