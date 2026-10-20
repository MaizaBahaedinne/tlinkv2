<?php

namespace App\Support\OrgChart;

use App\Models\Club;
use App\Models\Department;
use App\Models\Entity;
use App\Models\Governorate;
use App\Models\Institution;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Export / import de l'organigramme (JSON complet, CSV a plat).
 */
class OrgChartTransfer
{
    public const VERSION = 1;
    public const MAX_NODES = 3000;

    /**
     * @return array<string, mixed>
     */
    public function exportJson(): array
    {
        $clubs = Club::query()->get(['id', 'name', 'slug'])->keyBy('id');
        $governorates = Governorate::query()->get(['id', 'name', 'slug'])->keyBy('id');
        $entities = Entity::query()->get(['id', 'slug'])->keyBy('id');
        $departments = Department::query()->get(['id', 'slug'])->keyBy('id');
        $institutions = Institution::query()->get(['id', 'slug', 'institution_type'])->keyBy('id');

        return [
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'nodes' => OrgNode::query()->orderBy('id')->get()->map(fn (OrgNode $node): array => [
                'id' => $node->id,
                'parent_id' => $node->parent_id,
                'relation_type' => $node->relation_type,
                'kind' => $node->kind,
                'category' => $node->category,
                'title' => $node->title,
                'subtitle' => $node->subtitle,
                'description' => $node->description,
                'club_slug' => $node->club_id ? ($clubs[$node->club_id]->slug ?? null) : null,
                'club_name' => $node->club_id ? ($clubs[$node->club_id]->name ?? null) : null,
                'governorate_slug' => $node->governorate_id ? ($governorates[$node->governorate_id]->slug ?? null) : null,
                'governorate_name' => $node->governorate_id ? ($governorates[$node->governorate_id]->name ?? null) : null,
                'entity_slug' => $node->entity_id ? ($entities[$node->entity_id]->slug ?? null) : null,
                'department_slug' => $node->department_id ? ($departments[$node->department_id]->slug ?? null) : null,
                'institution_slug' => $node->institution_id ? ($institutions[$node->institution_id]->slug ?? null) : null,
                'institution_type' => $node->institution_id ? ($institutions[$node->institution_id]->institution_type ?? null) : null,
                'validation_status' => $node->validation_status,
                'validation_note' => $node->validation_note,
                'children_layout' => $node->children_layout,
                'is_collapsed' => $node->is_collapsed,
                'pos_dx' => $node->pos_dx,
                'pos_dy' => $node->pos_dy,
                'sort_order' => $node->sort_order,
                'system_key' => $node->system_key,
            ])->all(),
            'people' => OrgPerson::query()->orderBy('id')->get()->map(fn (OrgPerson $person): array => [
                'id' => $person->id,
                'full_name' => $person->full_name,
                'job_title' => $person->job_title,
                'phone' => $person->phone,
                'email' => $person->email,
                'photo_path' => $person->photo_path,
                'contact_visible' => $person->contact_visible,
                'notes' => $person->notes,
            ])->all(),
            'assignments' => OrgAssignment::query()->orderBy('id')->get()->map(fn (OrgAssignment $assignment): array => [
                'node_id' => $assignment->node_id,
                'person_id' => $assignment->person_id,
                'starts_at' => $assignment->starts_at?->toDateString(),
                'ends_at' => $assignment->ends_at?->toDateString(),
            ])->all(),
        ];
    }

    /**
     * Lignes CSV a plat (un bloc par ligne). Les cellules sont neutralisees contre l'injection de formules.
     *
     * @return array<int, array<int, string>>
     */
    public function csvRows(bool $includeContacts): array
    {
        $nodes = OrgNode::query()->with(['club:id,name', 'governorate:id,name', 'entity:id,name', 'department:id,name', 'institution:id,name'])->orderBy('id')->get();
        $parents = $nodes->pluck('title', 'id');
        $assignments = OrgAssignment::query()->with('person')->get()->groupBy('node_id');

        $header = ['id', 'parent_id', 'responsable', 'relation', 'type', 'categorie', 'poste', 'complement', 'club', 'gouvernorat', 'entite', 'departement', 'institution', 'validation', 'occupants'];

        if ($includeContacts) {
            $header[] = 'emails';
            $header[] = 'telephones';
        }

        $rows = [$header];

        foreach ($nodes as $node) {
            $people = ($assignments[$node->id] ?? collect())->pluck('person')->filter();

            $row = [
                (string) $node->id,
                (string) ($node->parent_id ?? ''),
                (string) ($node->parent_id ? ($parents[$node->parent_id] ?? '') : ''),
                OrgNode::RELATIONS[$node->relation_type] ?? $node->relation_type,
                OrgNode::KINDS[$node->kind] ?? $node->kind,
                OrgNode::CATEGORIES[$node->category] ?? $node->category,
                $node->title,
                (string) $node->subtitle,
                (string) $node->club?->name,
                (string) $node->governorate?->name,
                (string) $node->entity?->name,
                (string) $node->department?->name,
                (string) $node->institution?->name,
                OrgNode::VALIDATIONS[$node->validation_status] ?? $node->validation_status,
                $people->pluck('full_name')->implode(' | '),
            ];

            if ($includeContacts) {
                $row[] = $people->pluck('email')->filter()->implode(' | ');
                $row[] = $people->pluck('phone')->filter()->implode(' | ');
            }

            $rows[] = array_map([$this, 'neutralize'], $row);
        }

        return $rows;
    }

    /**
     * Remplace l'organigramme par le contenu d'un export JSON, apres validation complete.
     *
     * @param array<string, mixed> $payload
     * @return array{nodes: int, people: int, assignments: int}
     *
     * @throws ValidationException
     */
    public function import(array $payload): array
    {
        $data = Validator::make($payload, [
            'version' => ['required', 'integer', 'in:'.self::VERSION],
            'nodes' => ['required', 'array', 'min:1', 'max:'.self::MAX_NODES],
            'nodes.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'nodes.*.parent_id' => ['nullable', 'integer'],
            'nodes.*.relation_type' => ['required', Rule::in(array_keys(OrgNode::RELATIONS))],
            'nodes.*.kind' => ['required', Rule::in(array_keys(OrgNode::KINDS))],
            'nodes.*.category' => ['required', Rule::in(array_keys(OrgNode::CATEGORIES))],
            'nodes.*.title' => ['required', 'string', 'max:255'],
            'nodes.*.subtitle' => ['nullable', 'string', 'max:255'],
            'nodes.*.description' => ['nullable', 'string', 'max:5000'],
            'nodes.*.club_slug' => ['nullable', 'string', 'max:255'],
            'nodes.*.club_name' => ['nullable', 'string', 'max:255'],
            'nodes.*.governorate_slug' => ['nullable', 'string', 'max:255'],
            'nodes.*.entity_slug' => ['nullable', 'string', 'max:255'],
            'nodes.*.department_slug' => ['nullable', 'string', 'max:255'],
            'nodes.*.institution_slug' => ['nullable', 'string', 'max:255'],
            'nodes.*.institution_type' => ['nullable', 'string', 'max:40'],
            'nodes.*.validation_status' => ['required', Rule::in(array_keys(OrgNode::VALIDATIONS))],
            'nodes.*.validation_note' => ['nullable', 'string', 'max:500'],
            'nodes.*.children_layout' => ['required', Rule::in(array_keys(OrgNode::LAYOUTS))],
            'nodes.*.is_collapsed' => ['required', 'boolean'],
            'nodes.*.pos_dx' => ['nullable', 'integer', 'between:-100000,100000'],
            'nodes.*.pos_dy' => ['nullable', 'integer', 'between:-100000,100000'],
            'nodes.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'nodes.*.system_key' => ['nullable', 'string', 'max:80', 'distinct'],
            'people' => ['present', 'array', 'max:'.self::MAX_NODES],
            'people.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'people.*.full_name' => ['required', 'string', 'max:255'],
            'people.*.job_title' => ['nullable', 'string', 'max:255'],
            'people.*.phone' => ['nullable', 'string', 'max:40'],
            'people.*.email' => ['nullable', 'email', 'max:255'],
            'people.*.photo_path' => ['nullable', 'string', 'max:255', 'regex:#^orgchart/people/[A-Za-z0-9._-]+$#'],
            'people.*.contact_visible' => ['nullable', 'boolean'],
            'people.*.notes' => ['nullable', 'string', 'max:2000'],
            'assignments' => ['present', 'array'],
            'assignments.*.node_id' => ['required', 'integer'],
            'assignments.*.person_id' => ['required', 'integer'],
            'assignments.*.starts_at' => ['nullable', 'date'],
            'assignments.*.ends_at' => ['nullable', 'date'],
        ])->validate();

        $nodes = collect($data['nodes']);
        $ids = $nodes->pluck('id')->all();

        $this->assertStructure($nodes->all(), $ids);

        $personIds = collect($data['people'])->pluck('id')->all();

        foreach ($data['assignments'] as $assignment) {
            if (! in_array($assignment['node_id'], $ids, true) || ! in_array($assignment['person_id'], $personIds, true)) {
                throw ValidationException::withMessages(['assignments' => 'Une affectation référence un bloc ou une personne inexistant.']);
            }
        }

        return OrgChartLinker::withoutSync(fn (): array => DB::transaction(function () use ($data): array {
            OrgAssignment::query()->delete();
            OrgNode::query()->update(['parent_id' => null]);
            OrgNode::query()->delete();
            OrgPerson::query()->delete();

            $personMap = [];

            foreach ($data['people'] as $person) {
                $created = OrgPerson::query()->create([
                    'full_name' => $person['full_name'],
                    'job_title' => $person['job_title'] ?? null,
                    'phone' => $person['phone'] ?? null,
                    'email' => $person['email'] ?? null,
                    'photo_path' => $person['photo_path'] ?? null,
                    'contact_visible' => (bool) ($person['contact_visible'] ?? true),
                    'notes' => $person['notes'] ?? null,
                ]);
                $personMap[$person['id']] = $created->id;
            }

            $nodeMap = [];

            foreach ($this->orderedByDepth($data['nodes']) as $node) {
                [$clubId, $governorateId] = $this->resolveLinks($node);
                [$entityId, $departmentId, $institutionId] = $this->resolveStructure($node);

                $created = OrgNode::query()->create([
                    'parent_id' => $node['parent_id'] !== null ? $nodeMap[$node['parent_id']] : null,
                    'relation_type' => $node['relation_type'],
                    'kind' => $node['kind'],
                    'category' => $node['category'],
                    'title' => $node['title'],
                    'subtitle' => $node['subtitle'] ?? null,
                    'description' => $node['description'] ?? null,
                    'club_id' => $clubId,
                    'governorate_id' => $governorateId,
                    'entity_id' => $entityId,
                    'department_id' => $departmentId,
                    'institution_id' => $institutionId,
                    'validation_status' => $node['validation_status'],
                    'validation_note' => $node['validation_note'] ?? null,
                    'children_layout' => $node['children_layout'],
                    'is_collapsed' => (bool) $node['is_collapsed'],
                    'pos_dx' => (int) ($node['pos_dx'] ?? 0),
                    'pos_dy' => (int) ($node['pos_dy'] ?? 0),
                    'sort_order' => (int) ($node['sort_order'] ?? 0),
                    'system_key' => $node['system_key'] ?? null,
                ]);
                $nodeMap[$node['id']] = $created->id;
            }

            foreach ($data['assignments'] as $assignment) {
                OrgAssignment::query()->firstOrCreate(
                    ['node_id' => $nodeMap[$assignment['node_id']], 'person_id' => $personMap[$assignment['person_id']]],
                    ['starts_at' => $assignment['starts_at'] ?? null, 'ends_at' => $assignment['ends_at'] ?? null]
                );
            }

            return [
                'nodes' => count($nodeMap),
                'people' => count($personMap),
                'assignments' => count($data['assignments']),
            ];
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, int> $ids
     *
     * @throws ValidationException
     */
    private function assertStructure(array $nodes, array $ids): void
    {
        $roots = 0;
        $parentOf = [];
        $attachmentIds = [];

        foreach ($nodes as $node) {
            $parentOf[$node['id']] = $node['parent_id'] ?? null;

            if ($node['relation_type'] === 'attachment') {
                $attachmentIds[$node['id']] = true;
            }

            if (($node['parent_id'] ?? null) === null) {
                $roots++;

                if ($node['relation_type'] !== 'hierarchy') {
                    throw ValidationException::withMessages(['nodes' => 'Le sommet ne peut pas être un rattachement.']);
                }
            } elseif (! in_array($node['parent_id'], $ids, true)) {
                throw ValidationException::withMessages(['nodes' => 'Le bloc « '.$node['title'].' » référence un responsable inexistant.']);
            }
        }

        if ($roots !== 1) {
            throw ValidationException::withMessages(['nodes' => 'L’organigramme doit avoir exactement un sommet ('.$roots.' trouvé(s)).']);
        }

        foreach ($parentOf as $id => $parentId) {
            if ($parentId !== null && isset($attachmentIds[$parentId])) {
                throw ValidationException::withMessages(['nodes' => 'Un bloc en rattachement ne peut pas avoir de sous-blocs.']);
            }

            $seen = [$id => true];
            $current = $parentId;

            while ($current !== null) {
                if (isset($seen[$current])) {
                    throw ValidationException::withMessages(['nodes' => 'Cycle hiérarchique détecté dans le fichier importé.']);
                }

                $seen[$current] = true;
                $current = $parentOf[$current] ?? null;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function orderedByDepth(array $nodes): array
    {
        $parentOf = [];

        foreach ($nodes as $node) {
            $parentOf[$node['id']] = $node['parent_id'] ?? null;
        }

        $depth = static function (int $id) use ($parentOf): int {
            $level = 0;

            while (($parentOf[$id] ?? null) !== null) {
                $id = $parentOf[$id];
                $level++;
            }

            return $level;
        };

        usort($nodes, static fn (array $a, array $b): int => [$depth($a['id']), $a['sort_order'] ?? 0, $a['id']] <=> [$depth($b['id']), $b['sort_order'] ?? 0, $b['id']]);

        return $nodes;
    }

    /**
     * Entite, departement et institution retrouves par leur slug (absents : lien ignore).
     *
     * @param array<string, mixed> $node
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private function resolveStructure(array $node): array
    {
        $entityId = ! empty($node['entity_slug'])
            ? Entity::query()->where('slug', $node['entity_slug'])->value('id')
            : null;

        $departmentId = ! empty($node['department_slug'])
            ? Department::query()->where('slug', $node['department_slug'])->when($entityId, fn ($query) => $query->where('entity_id', $entityId))->value('id')
            : null;

        $institutionId = ! empty($node['institution_slug'])
            ? Institution::query()->where('slug', $node['institution_slug'])->when(! empty($node['institution_type']), fn ($query) => $query->where('institution_type', $node['institution_type']))->value('id')
            : null;

        return [$entityId, $departmentId, $institutionId];
    }

    /**
     * @param array<string, mixed> $node
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveLinks(array $node): array
    {
        $governorateId = ! empty($node['governorate_slug'])
            ? Governorate::query()->where('slug', $node['governorate_slug'])->value('id')
            : null;

        $clubId = null;

        if (! empty($node['club_slug'])) {
            $clubId = Club::query()->where('slug', $node['club_slug'])->value('id');

            if ($clubId === null && ! empty($node['club_name'])) {
                $clubId = Club::query()->create([
                    'name' => $node['club_name'],
                    'slug' => Str::slug($node['club_slug']) ?: Str::slug($node['club_name']),
                    'governorate_id' => $governorateId,
                    'status' => 'active',
                ])->id;
            }
        }

        return [$clubId, $governorateId];
    }

    private function neutralize(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
