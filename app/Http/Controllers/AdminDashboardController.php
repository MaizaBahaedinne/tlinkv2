<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\ApprovalRequest;
use App\Models\Department;
use App\Models\Comment;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Models\Institution;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Models\Club;
use App\Support\AccessScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    private const ROADMAP_GROUP = 'project';
    private const ROADMAP_KEY = 'admin_roadmap';

    public function index(Request $request): View|Response
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $roadmap = $this->loadRoadmap();
        $summary = $this->computeSummary($roadmap);
        $organization = $this->loadOrganizationOverview();
        $platform = $this->loadPlatformOverview();

        return response()->view('admin.index', [
            'roadmap' => $roadmap,
            'summary' => $summary,
            'organization' => $organization,
            'platform' => $platform,
            'statusOptions' => [
                'todo' => 'A faire',
                'in_progress' => 'En cours',
                'done' => 'Termine',
                'blocked' => 'Bloque',
            ],
        ]);
    }

    public function updateAction(Request $request): RedirectResponse
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $payload = $request->validate([
            'module_id' => ['required', 'string', 'max:120'],
            'action_id' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:todo,in_progress,done,blocked'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $roadmap = $this->loadRoadmap();

        foreach ($roadmap['modules'] as &$module) {
            if ($module['id'] !== $payload['module_id']) {
                continue;
            }

            foreach ($module['actions'] as &$action) {
                if ($action['id'] !== $payload['action_id']) {
                    continue;
                }

                $action['status'] = $payload['status'];
                $action['note'] = trim((string) ($payload['note'] ?? ''));
                $action['updated_at'] = now()->toDateTimeString();
                break;
            }

            break;
        }

        unset($module, $action);

        $this->saveRoadmap($roadmap);

        return back()->with('status', 'Roadmap mise a jour.');
    }

    public function structure(Request $request): View|Response
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        return response()->view('admin.structure', [
            'overview' => $this->loadStructureOverview(),
            'institutionTypes' => [
                'university' => 'Universite',
                'high_school' => 'Lycee',
                'other' => 'Autre',
            ],
            'clubStatuses' => [
                'draft' => 'Brouillon',
                'active' => 'Actif',
                'inactive' => 'Inactif',
                'archived' => 'Archive',
            ],
        ]);
    }

    public function storeEntity(Request $request): RedirectResponse
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Entity::query()->create([
            'name' => $payload['name'],
            'slug' => $this->resolveUniqueSlug(Entity::class, $payload['name']),
            'category' => $payload['category'],
            'description' => trim((string) ($payload['description'] ?? '')),
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);

        return back()->with('status', 'Entite ajoutee avec succes.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $payload = $request->validate([
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $baseSlug = Str::slug($payload['name']);
        $slug = $baseSlug !== '' ? $baseSlug : 'departement';
        $counter = 1;

        while (Department::query()->where('entity_id', $payload['entity_id'])->where('slug', $slug)->exists()) {
            $counter++;
            $slug = $baseSlug !== '' ? $baseSlug . '-' . $counter : 'departement-' . $counter;
        }

        Department::query()->create([
            'entity_id' => $payload['entity_id'],
            'name' => $payload['name'],
            'slug' => $slug,
            'description' => trim((string) ($payload['description'] ?? '')),
        ]);

        return back()->with('status', 'Departement ajoute avec succes.');
    }

    public function storeInstitution(Request $request): RedirectResponse
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $payload = $request->validate([
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'name' => ['required', 'string', 'max:120'],
            'institution_type' => ['required', 'in:university,high_school,other'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $baseSlug = Str::slug($payload['name']);
        $slug = $baseSlug !== '' ? $baseSlug : 'institution';
        $counter = 1;

        while (Institution::query()->where('institution_type', $payload['institution_type'])->where('slug', $slug)->exists()) {
            $counter++;
            $slug = $baseSlug !== '' ? $baseSlug . '-' . $counter : 'institution-' . $counter;
        }

        Institution::query()->create([
            'entity_id' => $payload['entity_id'] ?? null,
            'name' => $payload['name'],
            'slug' => $slug,
            'institution_type' => $payload['institution_type'],
            'description' => trim((string) ($payload['description'] ?? '')),
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ]);

        return back()->with('status', 'Institution ajoutee avec succes.');
    }

    public function storeClub(Request $request): RedirectResponse
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'status' => ['required', 'in:draft,active,inactive,archived'],
        ]);

        Club::query()->create([
            'name' => $payload['name'],
            'slug' => $this->resolveUniqueSlug(Club::class, $payload['name']),
            'entity_id' => $payload['entity_id'] ?? null,
            'department_id' => $payload['department_id'] ?? null,
            'institution_id' => $payload['institution_id'] ?? null,
            'governorate_id' => $payload['governorate_id'] ?? null,
            'status' => $payload['status'],
        ]);

        return back()->with('status', 'Club ajoute avec succes.');
    }

    private function loadStructureOverview(): array
    {
        $entities = Entity::query()
            ->withCount(['departments', 'institutions', 'clubs'])
            ->with([
                'departments' => fn ($query) => $query->select(['id', 'entity_id', 'name', 'slug'])->orderBy('name'),
                'institutions' => fn ($query) => $query->select(['id', 'entity_id', 'name', 'slug', 'institution_type', 'is_active'])->orderBy('name'),
                'clubs' => fn ($query) => $query
                    ->select(['id', 'name', 'slug', 'entity_id', 'department_id', 'institution_id', 'status'])
                    ->with(['department:id,name', 'institution:id,name'])
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        return [
            'entities' => $entities,
            'departments' => Department::query()->select(['id', 'entity_id', 'name'])->orderBy('name')->get(),
            'institutions' => Institution::query()->select(['id', 'entity_id', 'name', 'institution_type'])->orderBy('name')->get(),
            'governorates' => Governorate::query()->select(['id', 'name'])->orderBy('name')->get(),
        ];
    }

    private function resolveUniqueSlug(string $modelClass, string $value): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug !== '' ? $baseSlug : 'item';
        $counter = 1;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $counter++;
            $slug = ($baseSlug !== '' ? $baseSlug : 'item') . '-' . $counter;
        }

        return $slug;
    }

    private function loadRoadmap(): array
    {
        $setting = Setting::query()->firstOrNew([
            'group' => self::ROADMAP_GROUP,
            'key' => self::ROADMAP_KEY,
        ]);

        if (! $setting->exists || empty($setting->value)) {
            $roadmap = $this->defaultRoadmap();
            $setting->value = json_encode($roadmap, JSON_UNESCAPED_UNICODE);
            $setting->save();

            return $roadmap;
        }

        $decoded = json_decode((string) $setting->value, true);
        if (! is_array($decoded) || ! isset($decoded['modules']) || ! is_array($decoded['modules'])) {
            $roadmap = $this->defaultRoadmap();
            $setting->value = json_encode($roadmap, JSON_UNESCAPED_UNICODE);
            $setting->save();

            return $roadmap;
        }

        return $decoded;
    }

    private function saveRoadmap(array $roadmap): void
    {
        Setting::query()->updateOrCreate(
            [
                'group' => self::ROADMAP_GROUP,
                'key' => self::ROADMAP_KEY,
            ],
            [
                'value' => json_encode($roadmap, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function computeSummary(array $roadmap): array
    {
        $total = 0;
        $done = 0;
        $inProgress = 0;
        $blocked = 0;

        foreach ($roadmap['modules'] as &$module) {
            $moduleTotal = count($module['actions']);
            $moduleDone = 0;

            foreach ($module['actions'] as $action) {
                $total++;
                if ($action['status'] === 'done') {
                    $done++;
                    $moduleDone++;
                }
                if ($action['status'] === 'in_progress') {
                    $inProgress++;
                }
                if ($action['status'] === 'blocked') {
                    $blocked++;
                }
            }

            $module['progress'] = $moduleTotal > 0 ? (int) round(($moduleDone / $moduleTotal) * 100) : 0;
        }

        unset($module);

        return [
            'total_actions' => $total,
            'done_actions' => $done,
            'in_progress_actions' => $inProgress,
            'blocked_actions' => $blocked,
            'global_progress' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    private function loadOrganizationOverview(): array
    {
        $entities = Entity::query()
            ->withCount(['departments', 'institutions', 'clubs', 'memberships'])
            ->with([
                'departments:id,entity_id,name,slug',
                'institutions' => fn ($query) => $query
                    ->select(['id', 'entity_id', 'name', 'slug', 'institution_type'])
                    ->withCount('clubs')
                    ->orderBy('name'),
                'clubs:id,entity_id,department_id,institution_id,name,slug',
            ])
            ->orderBy('name')
            ->get();

        return [
            'total_entities' => $entities->count(),
            'total_departments' => $entities->sum('departments_count'),
            'total_institutions' => $entities->sum('institutions_count'),
            'total_clubs' => $entities->sum('clubs_count'),
            'entities' => $entities->map(function (Entity $entity): array {
                return [
                    'name' => $entity->name,
                    'slug' => $entity->slug,
                    'category' => $entity->category,
                    'description' => $entity->description,
                    'departments_count' => (int) $entity->departments_count,
                    'institutions_count' => (int) $entity->institutions_count,
                    'clubs_count' => (int) $entity->clubs_count,
                    'memberships_count' => (int) $entity->memberships_count,
                    'departments' => $entity->departments->map(fn ($department) => [
                        'name' => $department->name,
                        'slug' => $department->slug,
                    ])->values(),
                    'institutions' => $entity->institutions->map(fn ($institution) => [
                        'name' => $institution->name,
                        'slug' => $institution->slug,
                        'type' => $institution->institution_type,
                        'clubs_count' => (int) $institution->clubs_count,
                    ])->values(),
                    'clubs' => $entity->clubs->map(fn ($club) => [
                        'name' => $club->name,
                        'slug' => $club->slug,
                        'institution_id' => $club->institution_id,
                    ])->values(),
                ];
            })->values(),
        ];
    }

    private function loadPlatformOverview(): array
    {
        $now = now();

        return [
            'totals' => [
                'users' => User::query()->count(),
                'users_30d' => User::query()->where('created_at', '>=', $now->copy()->subDays(30))->count(),
                'posts' => Post::query()->count(),
                'posts_7d' => Post::query()->where('created_at', '>=', $now->copy()->subDays(7))->count(),
                'comments' => Comment::query()->count(),
                'reports_pending' => ModerationReport::query()->where('status', 'pending')->count(),
                'approvals_pending' => ApprovalRequest::query()->where('status', 'pending')->count(),
                'events' => FoundationEvent::query()->count(),
                'registrations' => EventRegistration::query()->count(),
            ],
            'recent_users' => User::query()
                ->select(['id', 'name', 'email', 'created_at'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (User $user): array => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'joined_at' => optional($user->created_at)->toDateTimeString(),
                ])
                ->values(),
            'recent_posts' => Post::query()
                ->with('author:id,name')
                ->select(['id', 'user_id', 'content', 'created_at'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Post $post): array => [
                    'author' => $post->author?->name ?? 'Utilisateur inconnu',
                    'content' => $post->content,
                    'created_at' => optional($post->created_at)->toDateTimeString(),
                ])
                ->values(),
            'recent_reports' => ModerationReport::query()
                ->select(['id', 'reason', 'status', 'created_at'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (ModerationReport $report): array => [
                    'reason' => $report->reason,
                    'status' => $report->status,
                    'created_at' => optional($report->created_at)->toDateTimeString(),
                ])
                ->values(),
        ];
    }

    private function defaultRoadmap(): array
    {
        return [
            'project_name' => 'T.Link V2 - Plan global',
            'updated_at' => now()->toDateTimeString(),
            'modules' => [
                [
                    'id' => 'foundation',
                    'title' => 'Module 1 - Foundation Technique',
                    'goal' => 'Stabiliser la base Laravel, auth, roles, permissions et CI/CD.',
                    'actions' => [
                        ['id' => 'foundation-auth', 'label' => 'Valider auth web/API et gestion session', 'status' => 'done', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'foundation-roles', 'label' => 'Finaliser matrice roles/permissions', 'status' => 'done', 'note' => 'Matrice centralisee et sync complete par role.', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'foundation-release', 'label' => 'Verifier pipeline release VPS sans interruption', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
                [
                    'id' => 'social',
                    'title' => 'Module 2 - Social & Feed',
                    'goal' => 'Consolider publication, commentaires, reactions et moderation.',
                    'actions' => [
                        ['id' => 'social-post', 'label' => 'Ameliorer experience creation post', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'social-moderation', 'label' => 'Actions moderation depuis UI admin', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'social-metrics', 'label' => 'Ajouter KPIs engagement', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
                [
                    'id' => 'governance',
                    'title' => 'Module 3 - Gouvernance & Workflow',
                    'goal' => 'Industrialiser adhesions, approbations et escalades.',
                    'actions' => [
                        ['id' => 'gov-membership', 'label' => 'Ecran de traitement adhesions en lot', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'gov-approval', 'label' => 'Historique decisions + filtres avances', 'status' => 'in_progress', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'gov-audit', 'label' => 'Rapprocher logs audit et workflows', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
                [
                    'id' => 'events',
                    'title' => 'Module 4 - Evenements & Mobilisation',
                    'goal' => 'Optimiser creation events et inscriptions participants.',
                    'actions' => [
                        ['id' => 'events-calendar', 'label' => 'Vue calendrier operationnelle', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'events-capacity', 'label' => 'Gestion capacite + waitlist UI', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'events-reminders', 'label' => 'Notifications rappels evenement', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
                [
                    'id' => 'ops',
                    'title' => 'Module 5 - Ops, Qualite & Securite',
                    'goal' => 'Renforcer observabilite, tests, hardening et docs d exploitation.',
                    'actions' => [
                        ['id' => 'ops-tests', 'label' => 'Couvrir workflows critiques par tests feature', 'status' => 'in_progress', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'ops-monitoring', 'label' => 'Mettre en place checks health et alerting', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'ops-playbook', 'label' => 'Rediger playbook incident + rollback', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
            ],
        ];
    }
}
