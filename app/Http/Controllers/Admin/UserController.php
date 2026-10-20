<?php

namespace App\Http\Controllers\Admin;

use App\Models\OrgAssignment;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends AdminController
{
    private const PERMISSIONS = ['entity.manage', 'role.manage', 'membership.manage'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'integer'],
        ]);

        $visible = $this->visibleEntityIds($request);

        $users = User::query()
            ->with([
                'profile:id,user_id,headline',
                'roleAssignments' => fn ($query) => $query->where('status', 'active')->with('role:id,name'),
            ])
            ->withCount(['posts', 'memberships'])
            ->when($visible !== null, fn ($query) => $this->restrictToEntities($query, $visible))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $query->where(fn ($inner) => $inner->where('name', 'like', "%{$needle}%")->orWhere('email', 'like', "%{$needle}%"));
            })
            ->when($filters['role'] ?? null, function ($query, $roleId): void {
                $query->whereHas('roleAssignments', fn ($assignments) => $assignments->where('role_id', $roleId)->where('status', 'active'));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $visible = $this->visibleEntityIds($request);

        if ($visible !== null) {
            abort_unless($this->restrictToEntities(User::query()->whereKey($user->id), $visible)->exists(), 403, 'Cet utilisateur est hors de votre périmètre.');
        }

        $context = $this->context($request);
        $roleScope = $context->scope('role.manage');

        $user->load('profile');

        $assignments = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->with(['role:id,name,scope_type', 'entity:id,name'])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->latest('id')
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'memberships' => $user->memberships()->with(['entity:id,name', 'department:id,name', 'club:id,name'])->latest('id')->get(),
            'orgPositions' => $this->orgPositions($user),
            'assignments' => $assignments,
            'recentPosts' => $user->posts()->latest('id')->limit(5)->get(['id', 'content', 'type', 'created_at']),
            'canManageRoles' => $roleScope !== null,
            'roleScope' => $roleScope,
            'roleOptions' => $roleScope !== null ? Role::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all() : [],
            'entityOptions' => $roleScope !== null ? $this->entityOptions($request, 'role.manage') : [],
            'canAssignGlobal' => $roleScope === [],
        ]);
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $scope = $this->context($request)->scope('role.manage');
        abort_if($scope === null, 403);

        $data = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            // Un administrateur limite a certaines entites ne peut attribuer que dans son perimetre.
            'entity_id' => [$scope === [] ? 'nullable' : 'required', 'integer', Rule::in(array_keys($this->entityOptions($request, 'role.manage')))],
            'ends_at' => ['nullable', 'date', 'after:today'],
        ]);

        $entityId = $data['entity_id'] ?? null;

        $duplicate = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('role_id', $data['role_id'])
            ->where('status', 'active')
            ->where('entity_id', $entityId)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['role_id' => 'Cet utilisateur possède déjà ce rôle sur ce périmètre.'])->withInput();
        }

        $assignment = RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $data['role_id'],
            'entity_id' => $entityId,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'ends_at' => $data['ends_at'] ?? null,
            'assigned_by' => $request->user()->id,
        ]);

        $this->audit($request, 'admin.role.assigned', $assignment, [
            'user_id' => $user->id,
            'role_id' => (int) $data['role_id'],
            'entity_id' => $entityId,
        ]);

        return back()->with('status', 'Rôle attribué à '.$user->name.'.');
    }

    public function revokeRole(Request $request, User $user, RoleAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->user_id === $user->id, 404);

        $context = $this->context($request);
        $scope = $context->scope('role.manage');
        abort_if($scope === null, 403);

        // Une affectation globale ne peut etre retiree que par un administrateur global.
        abort_unless(
            $scope === [] || ($assignment->entity_id !== null && in_array((int) $assignment->entity_id, $scope, true)),
            403,
            'Cette affectation est hors de votre périmètre.'
        );

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas retirer vos propres rôles.');
        }

        if ($assignment->status !== 'active') {
            return back()->with('error', 'Cette affectation est déjà terminée.');
        }

        $assignment->update(['status' => 'ended', 'ends_at' => now()->toDateString()]);

        $this->audit($request, 'admin.role.revoked', $assignment, [
            'user_id' => $user->id,
            'role_id' => $assignment->role_id,
            'entity_id' => $assignment->entity_id,
        ]);

        return back()->with('status', 'Rôle retiré à '.$user->name.'.');
    }

    /**
     * Entites visibles pour la gestion des utilisateurs. null = aucune restriction (perimetre global).
     *
     * @return array<int, int>|null
     */
    private function visibleEntityIds(Request $request): ?array
    {
        $context = $this->context($request);
        $ids = [];
        $hasAny = false;

        foreach (self::PERMISSIONS as $permission) {
            $scope = $context->scope($permission);

            if ($scope === null) {
                continue;
            }

            if ($scope === []) {
                return null;
            }

            $hasAny = true;
            $ids = array_merge($ids, $scope);
        }

        abort_unless($hasAny, 403, 'Acces refuse.');

        return array_values(array_unique($ids));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<User> $query
     * @param array<int, int> $entityIds
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function restrictToEntities($query, array $entityIds)
    {
        return $query->where(function ($inner) use ($entityIds): void {
            $inner->whereHas('memberships', fn ($memberships) => $memberships->whereIn('entity_id', $entityIds))
                ->orWhereHas('roleAssignments', fn ($assignments) => $assignments->whereIn('entity_id', $entityIds));
        });
    }

    /**
     * Postes actuellement occupés par l'utilisateur dans l'organigramme.
     *
     * @return \Illuminate\Support\Collection<int, OrgAssignment>
     */
    private function orgPositions(User $user)
    {
        $today = now()->toDateString();

        return OrgAssignment::query()
            ->whereHas('person', fn ($person) => $person->where('user_id', $user->id))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with(['node:id,title,subtitle', 'person:id,user_id,job_title'])
            ->orderBy('id')
            ->get();
    }
}
