<?php

namespace App\Http\Controllers\Admin;

use App\Models\Membership;
use App\Models\User;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestion des membres de « mon » entité : uniquement les périmètres des postes occupés,
 * sans leurs sous-périmètres (la vue d'ensemble reste dans Adhésions).
 * Un administrateur global n'a pas d'entité propre : il voit toutes les entités.
 */
class EntityMemberController extends AdminController
{
    private const PERMISSION = 'membership.manage';

    /** Statuts d'un membre ; les demandes en attente ou rejetées passent par Approbations. */
    private const MEMBER_STATUSES = [
        'approved' => 'Actifs',
        'suspended' => 'Suspendus',
        'archived' => 'Archivés',
    ];

    public function index(Request $request): View
    {
        $scopes = $this->entityScopes($request);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(self::MEMBER_STATUSES))],
            'scope' => ['nullable', Rule::in(array_keys($scopes))],
            'u' => ['nullable', 'string', 'min:2', 'max:100'],
        ]);

        $base = Membership::query()
            ->whereIn('scope', array_keys($scopes))
            ->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope));

        $counts = (clone $base)->whereIn('status', array_keys(self::MEMBER_STATUSES))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        $members = (clone $base)
            ->whereIn('status', array_keys(self::MEMBER_STATUSES))
            ->with(['user:id,name,email', 'node:id,title'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $like = '%'.addcslashes($needle, '%_\\').'%';
                $query->whereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $candidates = collect();
        $term = trim((string) ($filters['u'] ?? ''));

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $candidates = User::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'email']);
        }

        return view('admin.members.index', [
            'members' => $members,
            'filters' => $filters,
            'statuses' => self::MEMBER_STATUSES,
            'transitions' => MembershipController::TRANSITIONS,
            'counts' => $counts,
            'scopes' => $scopes,
            'isGlobal' => $this->context($request)->isGlobal(self::PERMISSION),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'canReview' => $this->context($request)->can('approval.review'),
            'term' => $term,
            'candidates' => $candidates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scopes = $this->entityScopes($request);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'scope' => ['required', Rule::in(array_keys($scopes))],
        ]);

        $membership = Membership::query()->where('user_id', $data['user_id'])->where('scope', $data['scope'])->latest('id')->first();

        if ($membership !== null && in_array($membership->status, ['pending', 'approved', 'suspended'], true)) {
            return back()->with('error', 'Cette personne est déjà rattachée à cette entité ('.(MembershipController::STATUSES[$membership->status] ?? $membership->status).').');
        }

        $attributes = [
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ];

        if ($membership === null) {
            $membership = Membership::query()->create($attributes + [
                'user_id' => $data['user_id'],
                'scope' => $data['scope'],
                'membership_type' => 'member',
                'starts_at' => now()->toDateString(),
            ]);
        } else {
            $membership->update($attributes);
        }

        $this->audit($request, 'admin.member.added', $membership, ['user_id' => (int) $data['user_id'], 'scope' => $data['scope']]);

        return redirect()->route('admin.members.index')->with('status', 'Membre ajouté à '.OrgScopes::label($data['scope']).'.');
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        abort_unless(isset($this->entityScopes($request)[$membership->scope]), 403, 'Ce membre n’appartient pas à votre entité.');

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'suspended', 'archived'])],
        ]);

        if (! in_array($data['status'], MembershipController::TRANSITIONS[$membership->status] ?? [], true)) {
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

        return back()->with('status', 'Statut du membre mis à jour.');
    }

    /**
     * Entités gérables : slug => nom. Les entités propres du gestionnaire, ou toutes pour un administrateur global.
     *
     * @return array<string, string>
     */
    private function entityScopes(Request $request): array
    {
        $own = $this->context($request)->ownScopes(self::PERMISSION);

        abort_if($own === null, 403, 'Acces refuse.');

        return $own === [] ? OrgScopes::all() : array_intersect_key(OrgScopes::all(), array_flip($own));
    }
}
