<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Support\AdminContext;
use App\Support\AuditLogger;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\OrgChart\OrgChartRules;
use App\Support\OrgChart\OrgChartTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OrgChartController extends Controller
{
    public function __construct(
        private readonly OrgChartInstaller $installer,
        private readonly OrgChartRules $rules,
        private readonly OrgChartTransfer $transfer,
    ) {
    }

    /* ------------------------------------------------------------------ Lecture */

    public function index(Request $request): View
    {
        return view('orgchart.index', [
            'canEdit' => $this->canEdit($request),
            'userName' => $request->user()->name,
            'adminUrl' => AdminContext::of($request)->canAny(['entity.manage', 'role.manage', 'membership.manage', 'approval.review', 'post.moderate', 'event.manage'])
                ? route('admin.dashboard')
                : null,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (! $this->installer->isInstalled()) {
            try {
                $this->installer->install();
            } catch (Throwable $exception) {
                // Une installation concurrente peut avoir deja cree les blocs (cle unique) : on relit simplement.
                report($exception);
            }
        }

        return response()->json($this->payload($request));
    }

    public function history(Request $request): JsonResponse
    {
        $this->requireEditor($request);

        $nodeId = $request->integer('node_id') ?: null;

        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->where('action', 'like', 'orgchart.%')
            ->when($nodeId, fn ($query) => $query->where('target_type', OrgNode::class)->where('target_id', $nodeId))
            ->latest('id')
            ->limit(80)
            ->get();

        return response()->json([
            'data' => $logs->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'label' => \App\Support\AuditLabels::label($log->action),
                'actor' => $log->actor?->name ?? 'Système',
                'target_id' => $log->target_id,
                'metadata' => $log->metadata ?? [],
                'at' => $log->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function exportJson(Request $request): JsonResponse
    {
        $this->requireEditor($request);

        return response()->json($this->transfer->exportJson(), 200, [
            'Content-Disposition' => 'attachment; filename="organigramme-'.now()->format('Y-m-d').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = $this->transfer->csvRows($this->canEdit($request));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : Excel lit correctement l'UTF-8

            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }

            fclose($out);
        }, 'organigramme-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ------------------------------------------------------------------ Blocs */

    public function storeNode(Request $request): JsonResponse
    {
        $this->requireEditor($request);

        $data = $this->validatedNode($request, null);

        $parent = OrgNode::query()->find($data['parent_id'] ?? null);
        $data['category'] = $data['category'] ?? $parent?->category ?? 'direction';

        $this->rules->assertValidPlacement(null, $data['parent_id'] ?? null, $data['relation_type']);

        $personIds = Arr::pull($data, 'person_ids');

        $node = DB::transaction(function () use ($data, $personIds): OrgNode {
            $node = OrgNode::query()->create($data + [
                'sort_order' => $this->nextSort($data['parent_id'] ?? null),
            ]);

            if ($personIds !== null) {
                $this->syncPeople($node, $personIds);
            }

            return $node;
        });

        $this->audit($request, 'orgchart.node.created', $node, ['title' => $node->title, 'parent_id' => $node->parent_id]);

        return $this->respond($request, $node->id, 201);
    }

    public function updateNode(Request $request, OrgNode $node): JsonResponse
    {
        $this->requireEditor($request);

        $data = $this->validatedNode($request, $node);

        if ($node->kind === 'club') {
            $data['kind'] = 'club';
        }

        $this->rules->assertValidPlacement($node, $data['parent_id'] ?? null, $data['relation_type']);

        $personIds = Arr::pull($data, 'person_ids');
        $before = $node->only(array_keys($data));

        DB::transaction(function () use ($node, $data, $personIds): void {
            if (($data['parent_id'] ?? null) !== $node->parent_id) {
                $data['sort_order'] = $this->nextSort($data['parent_id'] ?? null);
            }

            $node->update($data);

            if ($node->kind === 'club' && $node->club_id && $node->wasChanged('title')) {
                $node->club()->update(['name' => $node->title]);
            }

            if ($personIds !== null) {
                $this->syncPeople($node, $personIds);
            }
        });

        $changes = [];

        foreach ($before as $field => $old) {
            if ($old !== $node->{$field} && $node->wasChanged($field)) {
                $changes[$field] = ['from' => $old, 'to' => $node->{$field}];
            }
        }

        $this->audit($request, 'orgchart.node.updated', $node, ['title' => $node->title, 'changes' => $changes]);

        return $this->respond($request, $node->id);
    }

    public function destroyNode(Request $request, OrgNode $node): JsonResponse
    {
        $this->requireEditor($request);

        $cascade = $request->boolean('cascade');
        $plan = $this->rules->deletionPlan($node, $cascade);

        $title = $node->title;
        $this->audit($request, 'orgchart.node.deleted', $node, ['title' => $title, 'deleted_blocks' => count($plan)]);

        DB::transaction(function () use ($plan): void {
            foreach ($plan as $id) {
                OrgNode::query()->whereKey($id)->delete();
            }
        });

        return $this->respond($request, $node->parent_id);
    }

    public function reorder(Request $request, OrgNode $node): JsonResponse
    {
        $this->requireEditor($request);

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $siblings = OrgNode::query()->where('parent_id', $node->parent_id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $index = array_search($node->id, $siblings, true);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($siblings[$target])) {
            [$siblings[$index], $siblings[$target]] = [$siblings[$target], $siblings[$index]];

            DB::transaction(function () use ($siblings): void {
                foreach ($siblings as $position => $id) {
                    OrgNode::query()->whereKey($id)->update(['sort_order' => $position]);
                }
            });

            $this->audit($request, 'orgchart.node.reordered', $node, ['direction' => $direction]);
        }

        return $this->respond($request, $node->id);
    }

    /**
     * Enregistre les positions manuelles (decalages) des blocs deplaces a la souris.
     */
    public function saveLayout(Request $request): JsonResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'positions' => ['required', 'array', 'max:'.OrgChartTransfer::MAX_NODES],
            'positions.*.id' => ['required', 'integer', 'exists:org_nodes,id'],
            'positions.*.dx' => ['required', 'integer', 'between:-100000,100000'],
            'positions.*.dy' => ['required', 'integer', 'between:-100000,100000'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['positions'] as $position) {
                OrgNode::query()->whereKey($position['id'])->update(['pos_dx' => $position['dx'], 'pos_dy' => $position['dy']]);
            }
        });

        AuditLogger::record($request->user()->id, 'orgchart.layout.saved', null, null, ['blocks' => count($data['positions'])], $request->ip());

        return $this->respond($request, null);
    }

    public function storeClub(Request $request): JsonResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['regional', 'university', 'highschool'])],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'apply_template' => ['required', 'boolean'],
        ]);

        [$branchKey, $category] = match ($data['type']) {
            'regional' => ['regional-clubs', 'regional_club'],
            'university' => ['university-clubs', 'university_club'],
            'highschool' => ['highschool-clubs', 'highschool_club'],
        };

        $branch = OrgNode::query()->where('system_key', $branchKey)->first();

        if (! $branch) {
            throw ValidationException::withMessages(['type' => 'La branche de destination est introuvable : ajoutez-la ou restaurez le gabarit.']);
        }

        if ($data['type'] === 'regional' && empty($data['governorate_id'])) {
            throw ValidationException::withMessages(['governorate_id' => 'Choisissez le gouvernorat du club régional.']);
        }

        $node = $this->installer->createClub(
            $branch,
            $data['name'],
            $category,
            ! empty($data['governorate_id']) ? Governorate::query()->find($data['governorate_id']) : null,
            (bool) $data['apply_template'],
        );

        $this->audit($request, 'orgchart.club.created', $node, ['name' => $node->title, 'category' => $category, 'template' => (bool) $data['apply_template']]);

        return $this->respond($request, $node->id, 201);
    }

    public function applyTemplate(Request $request, OrgNode $node): JsonResponse
    {
        $this->requireEditor($request);

        if ($node->kind !== 'club') {
            throw ValidationException::withMessages(['node' => 'Le modèle d’équipe ne s’applique qu’à un club.']);
        }

        if (OrgNode::query()->where('parent_id', $node->id)->exists()) {
            throw ValidationException::withMessages(['node' => 'Ce club a déjà des postes : le modèle ne peut être appliqué que sur un club vide.']);
        }

        $count = $this->installer->applyTemplate($node);
        $this->audit($request, 'orgchart.club.template_applied', $node, ['title' => $node->title, 'positions' => $count]);

        return $this->respond($request, $node->id);
    }

    public function syncAssignments(Request $request, OrgNode $node): JsonResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'person_ids' => ['present', 'array', 'max:50'],
            'person_ids.*' => ['integer', 'distinct', 'exists:org_people,id'],
        ]);

        $this->syncPeople($node, $data['person_ids']);
        $this->audit($request, 'orgchart.assignments.updated', $node, ['title' => $node->title, 'person_ids' => $data['person_ids']]);

        return $this->respond($request, $node->id);
    }

    /* ------------------------------------------------------------------ Personnes */

    public function storePerson(Request $request): JsonResponse
    {
        $this->requireEditor($request);

        $data = $this->validatedPerson($request);
        $photo = Arr::pull($data, 'photo');
        Arr::pull($data, 'remove_photo');

        $person = OrgPerson::query()->create($data);

        if ($photo) {
            $person->update(['photo_path' => $photo->store('orgchart/people', 'public')]);
        }

        $this->audit($request, 'orgchart.person.created', $person, ['name' => $person->full_name]);

        $nodeId = $request->integer('assign_to_node') ?: null;

        if ($nodeId && OrgNode::query()->whereKey($nodeId)->exists()) {
            OrgAssignment::query()->firstOrCreate(['node_id' => $nodeId, 'person_id' => $person->id]);
            $this->audit($request, 'orgchart.assignments.updated', OrgNode::query()->findOrFail($nodeId), ['person_ids' => [$person->id]]);
        }

        return $this->respond($request, $nodeId, 201, ['person_id' => $person->id]);
    }

    public function updatePerson(Request $request, OrgPerson $person): JsonResponse
    {
        $this->requireEditor($request);

        $data = $this->validatedPerson($request);
        $photo = Arr::pull($data, 'photo');
        $remove = (bool) Arr::pull($data, 'remove_photo');

        $person->fill($data);

        if ($photo) {
            $this->deletePhoto($person);
            $person->photo_path = $photo->store('orgchart/people', 'public');
        } elseif ($remove) {
            $this->deletePhoto($person);
            $person->photo_path = null;
        }

        $person->save();

        $this->audit($request, 'orgchart.person.updated', $person, ['name' => $person->full_name]);

        return $this->respond($request, $request->integer('node_id') ?: null);
    }

    public function destroyPerson(Request $request, OrgPerson $person): JsonResponse
    {
        $this->requireEditor($request);

        $this->audit($request, 'orgchart.person.deleted', $person, ['name' => $person->full_name, 'assignments' => $person->assignments()->count()]);
        $this->deletePhoto($person);
        $person->delete();

        return $this->respond($request, null);
    }

    /* ------------------------------------------------------------------ Import */

    public function import(Request $request): JsonResponse
    {
        $this->requireEditor($request);

        $request->validate([
            'file' => ['required', 'file', 'mimes:json,txt', 'max:4096'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Confirmez le remplacement complet de l’organigramme.']);

        $payload = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['file' => 'Le fichier n’est pas un JSON valide.']);
        }

        $result = $this->transfer->import($payload);

        AuditLogger::record($request->user()->id, 'orgchart.imported', null, null, $result, $request->ip());

        return $this->respond($request, null, 200, ['imported' => $result]);
    }

    /* ------------------------------------------------------------------ Internes */

    private function canEdit(Request $request): bool
    {
        return AdminContext::of($request)->isGlobal('entity.manage');
    }

    private function requireEditor(Request $request): void
    {
        abort_unless($this->canEdit($request), 403, 'Seuls les administrateurs globaux peuvent modifier l’organigramme.');
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function respond(Request $request, ?int $selectedId, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json(['selected_id' => $selectedId, 'chart' => $this->payload($request)] + $extra, $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $canEdit = $this->canEdit($request);
        $today = now()->toDateString();

        $assignments = OrgAssignment::query()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->get(['node_id', 'person_id'])
            ->groupBy('node_id');

        $nodes = OrgNode::query()
            ->with(['club:id,name', 'governorate:id,name'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'can_edit' => $canEdit,
            'meta' => [
                'categories' => OrgNode::CATEGORIES,
                'kinds' => OrgNode::KINDS,
                'relations' => OrgNode::RELATIONS,
                'validations' => OrgNode::VALIDATIONS,
                'layouts' => OrgNode::LAYOUTS,
                'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name'])->all(),
            ],
            'nodes' => $nodes->map(fn (OrgNode $node): array => [
                'id' => $node->id,
                'parent_id' => $node->parent_id,
                'relation_type' => $node->relation_type,
                'kind' => $node->kind,
                'category' => $node->category,
                'title' => $node->title,
                'subtitle' => $node->subtitle,
                'description' => $node->description,
                'club_id' => $node->club_id,
                'club_name' => $node->club?->name,
                'governorate_id' => $node->governorate_id,
                'governorate_name' => $node->governorate?->name,
                'validation_status' => $node->validation_status,
                'validation_note' => $node->validation_note,
                'children_layout' => $node->children_layout,
                'is_collapsed' => $node->is_collapsed,
                'dx' => $node->pos_dx,
                'dy' => $node->pos_dy,
                'sort_order' => $node->sort_order,
                'system_key' => $node->system_key,
                'person_ids' => ($assignments[$node->id] ?? collect())->pluck('person_id')->all(),
            ])->all(),
            'people' => OrgPerson::query()->orderBy('full_name')->get()->map(function (OrgPerson $person) use ($canEdit): array {
                $showContact = $canEdit || $person->contact_visible;

                return [
                    'id' => $person->id,
                    'full_name' => $person->full_name,
                    'job_title' => $person->job_title,
                    'phone' => $showContact ? $person->phone : null,
                    'email' => $showContact ? $person->email : null,
                    'contact_visible' => $person->contact_visible,
                    'photo_url' => $person->photoUrl(),
                    'notes' => $canEdit ? $person->notes : null,
                    'user_id' => $canEdit ? $person->user_id : null,
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedNode(Request $request, ?OrgNode $node): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'kind' => [$node ? 'nullable' : 'required', Rule::in(['position', 'unit'])],
            'category' => ['nullable', Rule::in(array_keys(OrgNode::CATEGORIES))],
            'relation_type' => ['required', Rule::in(array_keys(OrgNode::RELATIONS))],
            'parent_id' => ['nullable', 'integer', 'exists:org_nodes,id'],
            'validation_status' => ['required', Rule::in(array_keys(OrgNode::VALIDATIONS))],
            'validation_note' => ['nullable', 'string', 'max:500'],
            'children_layout' => ['required', Rule::in(array_keys(OrgNode::LAYOUTS))],
            'is_collapsed' => ['required', 'boolean'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'person_ids' => ['nullable', 'array', 'max:50'],
            'person_ids.*' => ['integer', 'distinct', 'exists:org_people,id'],
        ]);

        $data['is_collapsed'] = (bool) $data['is_collapsed'];

        if ($node && empty($data['kind'])) {
            unset($data['kind']);
        }

        if ($node && empty($data['category'])) {
            unset($data['category']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPerson(Request $request): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\\-\\s]{3,40}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_visible' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ], ['phone.regex' => 'Le numéro ne doit contenir que des chiffres, espaces, +, -, ( ou ).']);

        $data['contact_visible'] = (bool) $data['contact_visible'];

        return $data;
    }

    /**
     * @param array<int, int> $personIds
     */
    private function syncPeople(OrgNode $node, array $personIds): void
    {
        $personIds = array_values(array_unique(array_map('intval', $personIds)));

        OrgAssignment::query()->where('node_id', $node->id)->whereNotIn('person_id', $personIds)->delete();

        foreach ($personIds as $personId) {
            OrgAssignment::query()->firstOrCreate(['node_id' => $node->id, 'person_id' => $personId]);
        }
    }

    private function nextSort(?int $parentId): int
    {
        $max = OrgNode::query()->where('parent_id', $parentId)->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    private function deletePhoto(OrgPerson $person): void
    {
        if ($person->photo_path && str_starts_with($person->photo_path, 'orgchart/people/')) {
            Storage::disk('public')->delete($person->photo_path);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function audit(Request $request, string $action, object $target, array $metadata = []): void
    {
        AuditLogger::record($request->user()->id, $action, $target::class, $target->getKey(), $metadata, $request->ip());
    }
}
