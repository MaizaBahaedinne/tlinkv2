<?php

namespace App\Http\Controllers\Admin;

use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\Profile;
use App\Models\User;
use App\Support\AccessLevels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
                'profile:id,user_id,headline,photo_path',
                'orgPerson.assignments' => fn ($query) => $query->where($current)->with('node:id,title,access_level'),
            ])
            ->withCount(['posts', 'memberships'])
            ->when($visible !== null, fn ($query) => $this->restrictToScopes($query, $visible))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $query->where(fn ($inner) => $inner->where('name', 'like', "%{$needle}%")->orWhere('first_name', 'like', "%{$needle}%")->orWhere('last_name', 'like', "%{$needle}%")->orWhere('email', 'like', "%{$needle}%"));
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
            'canEdit' => $this->context($request)->isGlobal('entity.manage'),
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
            'canEdit' => $this->context($request)->isGlobal('entity.manage'),
            'memberships' => $user->memberships()->with(['node:id,title'])->latest('id')->get(),
            'orgPositions' => $this->orgPositions($user),
            'recentPosts' => $user->posts()->latest('id')->limit(5)->get(['id', 'content', 'type', 'created_at']),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeEdit($request);

        $user->load('profile');

        return view('admin.users.edit', [
            'user' => $user,
            'profile' => $user->profile ?? new Profile(),
            'isSelf' => $user->is($request->user()),
            'genders' => ['male' => 'Homme', 'female' => 'Femme'],
            'governorates' => Governorate::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeEdit($request);

        $isSelf = $user->is($request->user());

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
            'deactivated_reason' => ['nullable', 'string', 'max:1000'],
            'headline' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone_secondary' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')],
            'delegation' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $user->first_name = trim($data['first_name']);
        $user->last_name = trim($data['last_name']);
        $user->email = $data['email'];

        $changed = ['first_name', 'last_name', 'email'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $changed[] = 'password';
        }

        // On ne peut pas desactiver son propre compte.
        $active = $isSelf ? true : (bool) ($data['is_active'] ?? $user->is_active);

        if ($active !== (bool) $user->is_active) {
            $changed[] = 'is_active';
        }

        $user->is_active = $active;
        $user->deactivated_at = $active ? null : ($user->deactivated_at ?? now());
        $user->deactivated_reason = $active ? null : ($data['deactivated_reason'] ?? null);
        $user->save();

        $profile = $user->profile()->firstOrNew([]);
        $profile->fill(array_intersect_key($data, array_flip([
            'headline', 'phone', 'phone_secondary', 'gender', 'birth_date', 'address', 'governorate_id', 'delegation', 'bio',
        ])));
        $profile->user_id = $user->id;

        if ($request->hasFile('avatar')) {
            $this->deleteAvatarFile($profile->photo_path);
            $profile->photo_path = $request->file('avatar')->store('profiles/avatars', 'public');
            $changed[] = 'avatar';
        } elseif ($request->boolean('remove_avatar') && $profile->photo_path) {
            $this->deleteAvatarFile($profile->photo_path);
            $profile->photo_path = null;
            $changed[] = 'avatar';
        }

        $profile->save();

        $this->audit($request, 'admin.user.updated', $user, ['fields' => $changed]);

        return redirect()->route('admin.users.show', $user)->with('status', 'Utilisateur mis à jour.');
    }

    /**
     * Modifier un compte (e-mail, mot de passe, activation) est reserve aux administrateurs globaux.
     */
    private function authorizeEdit(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Seuls les administrateurs globaux peuvent modifier un utilisateur.');
    }

    /**
     * Supprime l'ancienne photo, uniquement si elle a ete stockee par l'application.
     */
    private function deleteAvatarFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'profiles/avatars/')) {
            Storage::disk('public')->delete($path);
        }
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
