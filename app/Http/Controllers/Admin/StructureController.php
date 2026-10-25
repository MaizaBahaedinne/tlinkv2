<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\Post;
use App\Models\User;
use App\Support\AccessLevels;
use App\Support\AdminContext;
use App\Support\AuditLogger;
use App\Support\ClubRoles;
use App\Support\OrgAccess;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\OrgChart\OrgChartLinker;
use App\Support\OrgChart\OrgChartRules;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Gestion de la structure par tableaux et fiches : postes (droits, périmètre, titulaires) et clubs.
 * Lecture pour qui pilote l'organisation, écriture réservée aux administrateurs globaux
 * (même règle que l'organigramme graphique, qui reste la vue d'ensemble).
 */
class StructureController extends AdminController
{
    private const CLUB_FIELDS = ['status', 'email', 'facebook_url', 'founded_on', 'charter_signed', 'institution_name', 'institution_type', 'governorate_id'];

    public function __construct(
        private readonly OrgChartRules $rules,
        private readonly OrgChartInstaller $installer,
        private readonly OrgChartLinker $linker,
    ) {
    }

    /* ------------------------------------------------------------------ Tableau des postes */

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(array_keys(OrgNode::KINDS))],
            'scope' => ['nullable', Rule::in(OrgScopes::slugs())],
            'access' => ['nullable', Rule::in(array_keys(AccessLevels::LEVELS))],
            'category' => ['nullable', Rule::in(array_keys(OrgNode::CATEGORIES))],
            'holder' => ['nullable', Rule::in(['vacant', 'filled'])],
        ]);

        // Premiere visite : on montre les postes (seuls blocs qui portent des droits et des titulaires).
        if (! $request->has('kind')) {
            $filters['kind'] = 'position';
        }

        $scopeMap = OrgAccess::scopeMap();
        $current = $this->currentAssignments();

        $nodes = OrgNode::query()
            ->with(['assignments' => fn ($query) => $query->where($current)->with('person.user:id,name')])
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['access'] ?? null, fn ($query, $level) => $query->where('kind', 'position')->where('access_level', $level))
            ->when($filters['scope'] ?? null, function ($query, $scope) use ($scopeMap): void {
                $query->whereIn('id', array_keys(array_filter($scopeMap, fn (?string $effective): bool => $effective === $scope)));
            })
            ->when($filters['holder'] ?? null, function ($query, $holder) use ($current): void {
                $query->where('kind', 'position');
                $holder === 'vacant' ? $query->whereDoesntHave('assignments', $current) : $query->whereHas('assignments', $current);
            })
            ->when($filters['q'] ?? null, function ($query, $needle) use ($current): void {
                $like = '%'.addcslashes($needle, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $like)->orWhere('subtitle', 'like', $like)
                    ->orWhereHas('assignments', fn ($assignments) => $assignments->where($current)->whereHas('person', fn ($person) => $person
                        ->where('full_name', 'like', $like)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like)))));
            })
            ->orderBy('category')
            ->orderBy('subtitle')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $meta = $this->meta();

        return view('admin.structure.index', [
            'nodes' => $nodes,
            'filters' => $filters,
            'canEdit' => $this->canEdit($request),
            'levels' => AccessLevels::LEVELS,
            'permissions' => AccessLevels::PERMISSIONS,
            'scopes' => OrgScopes::all(),
            'kinds' => OrgNode::KINDS,
            'categories' => OrgNode::CATEGORIES,
            'scopeMap' => $scopeMap,
            'crumbs' => $nodes->getCollection()->mapWithKeys(fn (OrgNode $node): array => [$node->id => [
                'club' => $this->clubOf($node->id, $meta)?->title,
                'parent' => $node->parent_id ? ($meta[$node->parent_id]->title ?? null) : null,
            ]])->all(),
        ]);
    }

    public function bulkAccess(Request $request): RedirectResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'nodes' => ['required', 'array', 'min:1', 'max:200'],
            'nodes.*' => ['integer', 'exists:org_nodes,id'],
            'access_level' => ['required', Rule::in(array_keys(AccessLevels::LEVELS))],
        ], ['nodes.required' => 'Cochez au moins un poste.']);

        // Les postes d'une equipe type de club prennent le niveau de leur role (ecran « Roles des clubs »).
        $count = $this->keepingAdminAccess($request, fn (): int => OrgNode::query()
            ->whereIn('id', $data['nodes'])->where('kind', 'position')->whereNull('role_key')
            ->update(['access_level' => $data['access_level']]));

        AuditLogger::record($request->user()->id, 'orgchart.access.bulk_updated', null, null, ['level' => $data['access_level'], 'positions' => $count], $request->ip());

        return back()->with('status', $count.' poste(s) : niveau d’accès « '.AccessLevels::LEVELS[$data['access_level']].' ».');
    }

    /* ------------------------------------------------------------------ Tableau des clubs */

    public function clubs(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['university_club', 'highschool_club', 'regional_club'])],
            'status' => ['nullable', Rule::in(array_keys(OrgNode::CLUB_STATUSES))],
            'governorate_id' => ['nullable', 'integer'],
        ]);

        $clubs = $this->clubQuery($filters)
            ->with('governorate:id,name')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        $all = OrgNode::query()->get(['id', 'parent_id', 'kind', 'title']);
        $children = $all->groupBy('parent_id');
        $held = OrgAssignment::query()->where($this->currentAssignments())->distinct()->pluck('node_id')->flip();

        $rows = [];
        $presidentIds = [];

        foreach ($clubs as $club) {
            $team = collect();
            $queue = [$club->id];

            while ($queue !== []) {
                foreach ($children[array_shift($queue)] ?? [] as $child) {
                    $team->push($child);
                    $queue[] = $child->id;
                }
            }

            $positions = $team->where('kind', 'position');
            $president = $positions->first(fn (OrgNode $node): bool => str_starts_with($node->title, 'Président'));
            $president && $presidentIds[] = $president->id;

            $rows[$club->id] = [
                'club' => $club,
                'departments' => $team->where('kind', 'club_department')->count(),
                'positions' => $positions->count(),
                'filled' => $positions->filter(fn (OrgNode $node): bool => isset($held[$node->id]))->count(),
                'president_id' => $president?->id,
            ];
        }

        $presidents = OrgAssignment::query()->whereIn('node_id', $presidentIds)->where($this->currentAssignments())
            ->with('person.user:id,name')->get()->groupBy('node_id');

        return view('admin.structure.clubs', [
            'rows' => $rows,
            'clubs' => $clubs,
            'presidents' => $presidents,
            'filters' => $filters,
            'canEdit' => $this->canEdit($request),
            'governorates' => Governorate::query()->orderBy('name')->pluck('name', 'id')->all(),
            'statuses' => OrgNode::CLUB_STATUSES,
            'statusCounts' => OrgNode::query()->where('kind', 'club')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'total' => $clubs->total(),
        ]);
    }

    /**
     * Change le statut de clubs cochés, ou de tous les clubs du filtre en cours.
     * Un club inactif n'apparaît ni sur le site, ni dans l'API, ni dans les statistiques.
     */
    public function bulkClubStatus(Request $request): RedirectResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(OrgNode::CLUB_STATUSES))],
            'nodes' => ['nullable', 'array', 'max:500'],
            'nodes.*' => ['integer', 'exists:org_nodes,id'],
            'all_filtered' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['university_club', 'highschool_club', 'regional_club'])],
            'filter_status' => ['nullable', Rule::in(array_keys(OrgNode::CLUB_STATUSES))],
            'governorate_id' => ['nullable', 'integer'],
        ]);

        if ($request->boolean('all_filtered')) {
            $query = $this->clubQuery(['q' => $data['q'] ?? null, 'category' => $data['category'] ?? null, 'status' => $data['filter_status'] ?? null, 'governorate_id' => $data['governorate_id'] ?? null]);
        } elseif (! empty($data['nodes'])) {
            $query = OrgNode::query()->where('kind', 'club')->whereIn('id', $data['nodes']);
        } else {
            throw ValidationException::withMessages(['nodes' => 'Cochez au moins un club, ou appliquez à tous les clubs du filtre.']);
        }

        $count = $query->where('status', '!=', $data['status'])->update(['status' => $data['status']]);
        AuditLogger::record($request->user()->id, 'orgchart.club.status_bulk', null, null, ['status' => $data['status'], 'clubs' => $count], $request->ip());

        return back()->with('status', $count.' club(s) passé(s) en « '.OrgNode::CLUB_STATUSES[$data['status']].' ».');
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Illuminate\Database\Eloquent\Builder<OrgNode>
     */
    private function clubQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return OrgNode::query()
            ->where('kind', 'club')
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['governorate_id'] ?? null, fn ($query, $id) => $query->where('governorate_id', $id))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $like = '%'.addcslashes($needle, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $like)->orWhere('institution_name', 'like', $like));
            });
    }

    public function storeClub(Request $request): RedirectResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['regional', 'university', 'highschool'])],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'apply_template' => ['nullable', 'boolean'],
        ]);

        [$branchKey, $category] = match ($data['type']) {
            'regional' => ['regional-clubs', 'regional_club'],
            'university' => ['university-clubs', 'university_club'],
            'highschool' => ['highschool-clubs', 'highschool_club'],
        };

        $branch = OrgNode::query()->where('system_key', $branchKey)->first();

        if (! $branch) {
            throw ValidationException::withMessages(['type' => 'La branche de destination est introuvable : ouvrez d’abord l’organigramme.']);
        }

        if ($data['type'] === 'regional' && empty($data['governorate_id'])) {
            throw ValidationException::withMessages(['governorate_id' => 'Choisissez le gouvernorat du club régional.']);
        }

        $node = $this->installer->createClub(
            $branch,
            $data['name'],
            $category,
            ! empty($data['governorate_id']) ? Governorate::query()->find($data['governorate_id']) : null,
            $request->boolean('apply_template', true),
        );

        $this->audit($request, 'orgchart.club.created', $node, ['name' => $node->title, 'category' => $category]);

        return redirect()->route('admin.postes.edit', $node)->with('status', 'Club créé.');
    }

    public function alignClubs(Request $request): RedirectResponse
    {
        $this->requireEditor($request);

        $changes = $this->installer->alignClubTeams();
        AuditLogger::record($request->user()->id, 'orgchart.club.aligned', null, null, ['changes' => $changes], $request->ip());

        return back()->with('status', $changes === 0
            ? 'Tous les clubs University / High School suivent déjà le modèle.'
            : $changes.' bloc(s) créé(s), déplacé(s) ou retiré(s) pour suivre le modèle.');
    }

    /* ------------------------------------------------------------------ Page d'un club */

    public function showClub(Request $request, OrgNode $node): View
    {
        abort_unless($node->kind === 'club', 404);

        $node->load('governorate:id,name');
        $current = $this->currentAssignments();

        $team = collect();
        $queue = [$node->id];

        while ($queue !== []) {
            $children = OrgNode::query()->where('parent_id', array_shift($queue))->orderBy('sort_order')->orderBy('id')->get();

            foreach ($children as $child) {
                $team->push($child);
                $queue[] = $child->id;
            }
        }

        $assignments = OrgAssignment::query()->whereIn('node_id', $team->pluck('id'))->where($current)
            ->with('person.user:id,name')->get()->groupBy('node_id');
        $positions = $team->where('kind', 'position');
        $filled = $positions->filter(fn (OrgNode $position): bool => isset($assignments[$position->id]))->count();
        $nodeIds = $team->pluck('id')->push($node->id);

        $memberships = Membership::query()->where('node_id', $node->id);
        $events = FoundationEvent::query()->where('node_id', $node->id);
        $posts = Post::query()->where('node_id', $node->id);

        return view('admin.structure.club', [
            'club' => $node,
            'canEdit' => $this->canEdit($request),
            'tree' => $team->groupBy('parent_id'),
            'assignments' => $assignments,
            'positions' => $positions,
            'filled' => $filled,
            'memberStats' => (clone $memberships)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'recentMembers' => (clone $memberships)->with('user:id,name')->latest('id')->limit(6)->get(),
            'postCount' => (clone $posts)->count(),
            'recentPosts' => (clone $posts)->with('author:id,name')->latest('id')->limit(5)->get(),
            'eventCount' => (clone $events)->count(),
            'upcomingEvents' => (clone $events)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
            'pastEvents' => (clone $events)->where('starts_at', '<', now())->count(),
            'logs' => AuditLog::query()->where('target_type', OrgNode::class)->whereIn('target_id', $nodeIds)
                ->with('actor:id,name')->latest('id')->limit(8)->get(),
            'levels' => AccessLevels::LEVELS,
        ]);
    }

    /* ------------------------------------------------------------------ Roles des clubs */

    public function roles(Request $request): View
    {
        $counts = OrgNode::query()->whereNotNull('role_key')->whereIn('category', ClubRoles::CATEGORIES)
            ->selectRaw('role_key, count(*) as total')->groupBy('role_key')->pluck('total', 'role_key')->all();

        return view('admin.structure.roles', [
            'canEdit' => $this->canEdit($request),
            'roles' => ClubRoles::all(),
            'current' => ClubRoles::levels(),
            'counts' => $counts,
            'levels' => array_intersect_key(AccessLevels::LEVELS, array_flip(ClubRoles::ALLOWED_LEVELS)),
            'levelPermissions' => collect(AccessLevels::LEVELS)->map(fn ($label, $level): array => array_map(
                fn (string $permission): string => AccessLevels::PERMISSIONS[$permission],
                AccessLevels::permissions($level)
            ))->all(),
        ]);
    }

    public function updateRoles(Request $request): RedirectResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'levels' => ['required', 'array'],
            'levels.*' => ['required', Rule::in(ClubRoles::ALLOWED_LEVELS)],
        ]);

        $updated = ClubRoles::save($data['levels']);
        AuditLogger::record($request->user()->id, 'orgchart.club_roles.updated', null, null, ['levels' => ClubRoles::levels(), 'positions' => $updated], $request->ip());

        return back()->with('status', 'Droits enregistrés : '.$updated.' poste(s) de club mis à jour.');
    }

    /* ------------------------------------------------------------------ Fiche d'un bloc */

    public function edit(Request $request, OrgNode $node): View
    {
        $canEdit = $this->canEdit($request);
        $meta = $this->meta();
        $current = $this->currentAssignments();

        $node->load(['assignments' => fn ($query) => $query->where($current)->with('person.user.profile:id,user_id,phone')]);

        $term = $canEdit ? trim((string) $request->query('u', '')) : '';
        $found = collect();

        if (mb_strlen($term) >= 2 && $node->kind === 'position') {
            $query = User::query()->where('is_active', true);

            foreach (array_slice(preg_split('/\s+/u', mb_substr($term, 0, 80)), 0, 4) as $word) {
                $like = '%'.addcslashes($word, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like));
            }

            $found = $query->orderBy('name')->limit(10)->get(['id', 'name', 'email']);
        }

        $ancestors = [];

        for ($id = $node->parent_id, $guard = 0; $id && $guard < 80; $guard++) {
            $parent = $meta[$id] ?? null;

            if (! $parent) {
                break;
            }

            array_unshift($ancestors, $parent);
            $id = $parent->parent_id;
        }

        $effectiveScope = OrgAccess::scopeOf($node);

        return view('admin.structure.edit', [
            'node' => $node,
            'canEdit' => $canEdit,
            'ancestors' => $ancestors,
            'parentOptions' => $canEdit && $node->parent_id !== null ? $this->parentOptions($node, $meta) : [],
            'children' => $node->children()->with(['assignments' => fn ($query) => $query->where($current)->with('person.user:id,name')])->get(),
            'holders' => $node->assignments,
            'found' => $found,
            'term' => $term,
            'effectiveScope' => $effectiveScope,
            'scopeInherited' => $node->scope === null && $effectiveScope !== null,
            'levels' => AccessLevels::LEVELS,
            'levelPermissions' => collect(AccessLevels::LEVELS)->map(fn ($label, $level): array => array_map(
                fn (string $permission): string => AccessLevels::PERMISSIONS[$permission],
                AccessLevels::permissions($level)
            ))->all(),
            'scopes' => OrgScopes::all(),
            'kinds' => array_intersect_key(OrgNode::KINDS, array_flip(OrgNode::MANUAL_KINDS)),
            'capacities' => OrgNode::CAPACITIES,
            'governorates' => Governorate::query()->orderBy('name')->pluck('name', 'id')->all(),
            'clubStatuses' => OrgNode::CLUB_STATUSES,
            'institutionTypes' => OrgNode::INSTITUTION_TYPES,
            'hasChildren' => $node->children()->exists(),
        ]);
    }

    public function update(Request $request, OrgNode $node): RedirectResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => ['nullable', Rule::in(OrgNode::MANUAL_KINDS)],
            'parent_id' => ['nullable', 'integer', 'exists:org_nodes,id'],
            'capacity' => ['nullable', Rule::in(array_keys(OrgNode::CAPACITIES))],
            'access_level' => ['nullable', Rule::in(array_keys(AccessLevels::LEVELS))],
            'scope' => ['nullable', Rule::in(OrgScopes::slugs())],
            'status' => ['nullable', Rule::in(array_keys(OrgNode::CLUB_STATUSES))],
            'email' => ['nullable', 'email', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'founded_on' => ['nullable', 'date'],
            'charter_signed' => ['nullable', 'boolean'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'institution_type' => ['nullable', Rule::in(array_keys(OrgNode::INSTITUTION_TYPES))],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
        ]);

        $kind = $node->kind === 'club' ? 'club' : ($data['kind'] ?? $node->kind);
        $parentId = $node->parent_id === null ? null : (int) ($data['parent_id'] ?? $node->parent_id);

        $this->rules->assertValidPlacement($node, $parentId, $node->relation_type);
        $this->rules->assertValidKind($kind, $parentId);

        $attributes = Arr::only($data, ['title', 'subtitle', 'description', 'scope']) + ['kind' => $kind, 'parent_id' => $parentId];

        if ($kind === 'position') {
            $attributes['capacity'] = $data['capacity'] ?? $node->capacity ?? 'single';
            // Poste d'une equipe type de club : le niveau vient du role, pas de la fiche.
            $attributes['access_level'] = $node->role_key !== null ? $node->access_level : ($data['access_level'] ?? $node->access_level);
        } else {
            $attributes['capacity'] = 'single';
            $attributes['access_level'] = 'none';
        }

        if ($kind === 'club') {
            $attributes += Arr::only($data, self::CLUB_FIELDS);
            $attributes['charter_signed'] = $request->boolean('charter_signed');
            $attributes['status'] = $data['status'] ?? $node->status;
        }

        if ($parentId !== $node->parent_id) {
            $attributes['sort_order'] = $this->nextSort($parentId);
        }

        $before = $node->only(array_keys($attributes));

        $this->keepingAdminAccess($request, function () use ($node, $attributes): void {
            $node->update($attributes);

            if ($node->kind === 'club') {
                $this->linker->placeClub($node);
            }

            $this->rules->assertCanHold($node, $this->rules->currentPeopleCount($node));
        });

        $changes = [];

        foreach ($before as $field => $old) {
            if ($node->wasChanged($field)) {
                $changes[$field] = ['from' => $old, 'to' => $node->{$field}];
            }
        }

        $this->audit($request, 'orgchart.node.updated', $node, ['title' => $node->title, 'changes' => $changes]);

        return redirect()->route('admin.postes.edit', $node)->with('status', 'Fiche enregistrée.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'parent_id' => ['required', 'integer', 'exists:org_nodes,id'],
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in(OrgNode::MANUAL_KINDS)],
            'capacity' => ['nullable', Rule::in(array_keys(OrgNode::CAPACITIES))],
            'access_level' => ['nullable', Rule::in(array_keys(AccessLevels::LEVELS))],
        ]);

        $parent = OrgNode::query()->findOrFail($data['parent_id']);

        $this->rules->assertValidPlacement(null, $parent->id, 'hierarchy');
        $this->rules->assertValidKind($data['kind'], $parent->id);

        $isPosition = $data['kind'] === 'position';

        $node = OrgNode::query()->create([
            'parent_id' => $parent->id,
            'relation_type' => 'hierarchy',
            'kind' => $data['kind'],
            'capacity' => $isPosition ? ($data['capacity'] ?? 'single') : 'single',
            'access_level' => $isPosition ? ($data['access_level'] ?? 'none') : 'none',
            'category' => $parent->category,
            'title' => $data['title'],
            'subtitle' => $parent->kind === 'club' ? $parent->title : $parent->subtitle,
            'sort_order' => $this->nextSort($parent->id),
        ]);

        $this->audit($request, 'orgchart.node.created', $node, ['title' => $node->title, 'parent_id' => $node->parent_id]);

        return redirect()->route('admin.postes.edit', $node)->with('status', 'Bloc créé.');
    }

    public function destroy(Request $request, OrgNode $node): RedirectResponse
    {
        $this->requireEditor($request);

        $plan = $this->rules->deletionPlan($node, $request->boolean('cascade'));
        $this->audit($request, 'orgchart.node.deleted', $node, ['title' => $node->title, 'deleted_blocks' => count($plan)]);

        $this->keepingAdminAccess($request, function () use ($plan): void {
            foreach ($plan as $id) {
                OrgNode::query()->whereKey($id)->delete();
            }
        });

        return redirect()->route($node->parent_id ? 'admin.postes.edit' : 'admin.postes.index', $node->parent_id)
            ->with('status', 'Bloc supprimé.');
    }

    public function applyTemplate(Request $request, OrgNode $node): RedirectResponse
    {
        $this->requireEditor($request);

        if ($node->kind !== 'club') {
            throw ValidationException::withMessages(['node' => 'L’équipe type ne s’applique qu’à un club.']);
        }

        if (OrgNode::query()->where('parent_id', $node->id)->exists()) {
            throw ValidationException::withMessages(['node' => 'Ce club a déjà des postes : l’équipe type ne s’applique qu’à un club vide.']);
        }

        $count = $this->installer->applyTemplate($node);
        $this->audit($request, 'orgchart.club.template_applied', $node, ['title' => $node->title, 'positions' => $count]);

        return back()->with('status', 'Équipe type appliquée : '.$count.' bloc(s) créé(s).');
    }

    /* ------------------------------------------------------------------ Titulaires */

    public function assign(Request $request, OrgNode $node): RedirectResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->findOrFail($data['user_id']);

        if (! $user->is_active) {
            throw ValidationException::withMessages(['user_id' => 'Ce compte est désactivé.']);
        }

        $already = OrgAssignment::query()->where('node_id', $node->id)
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))->exists();

        if (! $already) {
            $this->rules->assertCanHold($node, $this->rules->currentPeopleCount($node) + 1);
        }

        $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], [
            'full_name' => $user->name,
            'job_title' => $data['job_title'] ?? null,
            'contact_visible' => true,
        ]);

        OrgAssignment::query()->firstOrCreate(['node_id' => $node->id, 'person_id' => $person->id]);
        $this->audit($request, 'orgchart.assignments.updated', $node, ['title' => $node->title, 'user_id' => $user->id]);

        return redirect()->route('admin.postes.edit', $node)->with('status', $user->name.' est affecté(e) à « '.$node->title.' ».');
    }

    public function unassign(Request $request, OrgNode $node, OrgPerson $person): RedirectResponse
    {
        $this->requireEditor($request);

        $this->keepingAdminAccess($request, fn () => OrgAssignment::query()->where('node_id', $node->id)->where('person_id', $person->id)->delete());
        $this->audit($request, 'orgchart.assignments.updated', $node, ['title' => $node->title, 'removed_person_id' => $person->id]);

        return redirect()->route('admin.postes.edit', $node)->with('status', 'Titulaire retiré.');
    }

    /* ------------------------------------------------------------------ Internes */

    private function canEdit(Request $request): bool
    {
        return AdminContext::of($request)->isGlobal('entity.manage');
    }

    private function requireEditor(Request $request): void
    {
        abort_unless($this->canEdit($request), 403, 'Seuls les administrateurs globaux peuvent modifier la structure.');
    }

    /**
     * Affectations en cours (sans date de fin, ou dont la fin n'est pas passee).
     */
    private function currentAssignments(): \Closure
    {
        $today = now()->toDateString();

        return fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
    }

    /**
     * @return Collection<int, OrgNode>
     */
    private function meta(): Collection
    {
        return OrgNode::query()->get(['id', 'parent_id', 'kind', 'title', 'relation_type'])->keyBy('id');
    }

    private function clubOf(int $id, Collection $meta): ?OrgNode
    {
        for ($guard = 0; $id && $guard < 80; $guard++) {
            $node = $meta[$id] ?? null;

            if (! $node) {
                return null;
            }

            if ($node->kind === 'club') {
                return $node;
            }

            $id = (int) $node->parent_id;
        }

        return null;
    }

    /**
     * Rattachements proposes : dans un club, l'interieur du club ; ailleurs, la structure hors clubs.
     * Jamais le bloc lui-meme, ses descendants, ni un simple rattachement.
     *
     * @param Collection<int, OrgNode> $meta
     * @return array<int, string> id => chemin
     */
    private function parentOptions(OrgNode $node, Collection $meta): array
    {
        $excluded = array_flip(array_merge([$node->id], $this->rules->descendantIds($node->id)));
        $club = $node->kind === 'club' ? null : $this->clubOf((int) $node->parent_id, $meta);
        $inside = $club ? array_flip(array_merge([$club->id], $this->rules->descendantIds($club->id))) : null;

        $options = [];

        foreach ($meta as $candidate) {
            if (isset($excluded[$candidate->id]) || $candidate->relation_type === 'attachment') {
                continue;
            }

            if ($inside !== null ? ! isset($inside[$candidate->id]) : ($candidate->kind === 'club' || $this->clubOf((int) $candidate->parent_id, $meta) !== null)) {
                continue;
            }

            $path = [];

            for ($id = $candidate->id, $guard = 0; $id && $guard < 3; $guard++) {
                $step = $meta[$id] ?? null;
                $step && array_unshift($path, $step->title);
                $id = (int) ($step->parent_id ?? 0);
            }

            $options[$candidate->id] = implode(' › ', $path);
        }

        asort($options);

        return $options;
    }

    private function nextSort(?int $parentId): int
    {
        $max = OrgNode::query()->where('parent_id', $parentId)->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    /**
     * Les droits viennent des postes : une modification qui retirerait ses droits d'administrateur global
     * à celui qui la fait est annulée.
     *
     * @template T
     * @param \Closure(): T $mutation
     * @return T
     */
    private function keepingAdminAccess(Request $request, \Closure $mutation): mixed
    {
        return DB::transaction(function () use ($request, $mutation) {
            $result = $mutation();

            if (! (new AdminContext($request->user()))->isGlobal('entity.manage')) {
                throw ValidationException::withMessages(['access_level' => 'Cette modification vous retirerait vos droits d’administrateur global : elle est annulée.']);
            }

            return $result;
        });
    }
}
