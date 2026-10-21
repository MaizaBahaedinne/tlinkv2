<?php

namespace App\Http\Controllers\Admin;

use App\Models\OrgAssignment;
use App\Models\User;
use App\Support\AccessLevels;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends AdminController
{
    private const PERMISSIONS = ['entity.manage', 'membership.manage'];

    public function index(Request $request): View
    {
        $levels = array_diff_key(AccessLevels::LEVELS, [AccessLevels::NONE => true]);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'access' => ['nullable', Rule::in(array_keys($levels))],
        ]);

        $visible = $this->visibleScopes($request);
        $today = now()->toDateString();
        $current = fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today);

        $users = User::query()
            ->with([
                'profile:id,user_id,headline',
                'orgPerson.assignments' => fn ($query) => $query->where($current)->with('node:id,title,access_level'),
            ])
            ->withCount(['posts', 'memberships'])
            ->when($visible !== null, fn ($query) => $this->restrictToScopes($query, $visible))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $query->where(fn ($inner) => $inner->where('name', 'like', "%{$needle}%")->orWhere('email', 'like', "%{$needle}%"));
            })
            ->when($filters['access'] ?? null, function ($query, $level) use ($current): void {
                $query->whereHas('orgPerson.assignments', fn ($assignments) => $assignments->where($current)
                    ->whereHas('node', fn ($node) => $node->where('access_level', $level)));
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'levels' => $levels,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $visible = $this->visibleScopes($request);

        if ($visible !== null) {
            abort_unless($this->restrictToScopes(User::query()->whereKey($user->id), $visible)->exists(), 403, 'Cet utilisateur est hors de votre périmètre.');
        }

        $user->load('profile');

        return view('admin.users.show', [
            'user' => $user,
            'memberships' => $user->memberships()->with(['node:id,title'])->latest('id')->get(),
            'orgPositions' => $this->orgPositions($user),
            'recentPosts' => $user->posts()->latest('id')->limit(5)->get(['id', 'content', 'type', 'created_at']),
        ]);
    }

    /**
     * Perimetres visibles pour la gestion des utilisateurs. null = aucune restriction (perimetre global).
     *
     * @return array<int, string>|null
     */
    private function visibleScopes(Request $request): ?array
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
     * @param array<int, string> $scopes
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function restrictToScopes($query, array $scopes)
    {
        return $query->whereHas('memberships', fn ($memberships) => $memberships->whereIn('scope', $scopes));
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
            ->with(['node:id,title,subtitle,access_level', 'person:id,user_id,job_title'])
            ->orderBy('id')
            ->get();
    }
}
