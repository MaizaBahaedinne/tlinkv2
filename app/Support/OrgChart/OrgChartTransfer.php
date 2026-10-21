<?php

namespace App\Support\OrgChart;

use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\AccessLevels;
use App\Support\OrgScopes;
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
        $governorates = Governorate::query()->get(['id', 'name', 'slug'])->keyBy('id');

        return [
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'nodes' => OrgNode::query()->orderBy('id')->get()->map(fn (OrgNode $node): array => [
                'id' => $node->id,
                'parent_id' => $node->parent_id,
                'relation_type' => $node->relation_type,
                'kind' => $node->kind,
                'capacity' => $node->capacity,
                'access_level' => $node->access_level,
                'category' => $node->category,
                'title' => $node->title,
                'subtitle' => $node->subtitle,
                'description' => $node->description,
                'governorate_slug' => $node->governorate_id ? ($governorates[$node->governorate_id]->slug ?? null) : null,
                'governorate_name' => $node->governorate_id ? ($governorates[$node->governorate_id]->name ?? null) : null,
                'scope' => $node->scope,
                'status' => $node->status,
                'legacy_id' => $node->legacy_id,
                'email' => $node->email,
                'facebook_url' => $node->facebook_url,
                'founded_on' => $node->founded_on?->toDateString(),
                'charter_signed' => $node->charter_signed,
                'institution_name' => $node->institution_name,
                'institution_type' => $node->institution_type,
                'validation_status' => $node->validation_status,
                'validation_note' => $node->validation_note,
                'children_layout' => $node->children_layout,
                'is_collapsed' => $node->is_collapsed,
                'pos_dx' => $node->pos_dx,
                'pos_dy' => $node->pos_dy,
                'sort_order' => $node->sort_order,
                'system_key' => $node->system_key,
            ])->all(),
            'people' => OrgPerson::query()->with('user.profile')->orderBy('id')->get()->map(fn (OrgPerson $person): array => [
                'id' => $person->id,
                'full_name' => $person->displayName(),
                'job_title' => $person->job_title,
                'phone' => $person->displayPhone(),
                'email' => $person->displayEmail(),
                // Personne liee a un compte : le lien est restaure a l'import par l'e-mail du compte.
                'user_email' => $person->user?->email,
                'photo_path' => $person->user_id ? null : $person->photo_path,
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
        $nodes = OrgNode::query()->with(['governorate:id,name'])->orderBy('id')->get();
        $parents = $nodes->pluck('title', 'id');
        $assignments = OrgAssignment::query()->with('person.user.profile')->get()->groupBy('node_id');

        $header = ['id', 'parent_id', 'responsable', 'relation', 'type', 'categorie', 'poste', 'complement', 'gouvernorat', 'perimetre', 'etablissement', 'validation', 'occupants'];

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
                (string) $node->governorate?->name,
                (string) OrgScopes::label($node->scope),
                (string) $node->institution_name,
                OrgNode::VALIDATIONS[$node->validation_status] ?? $node->validation_status,
                $people->map(fn (OrgPerson $person): string => $person->displayName())->implode(' | '),
            ];

            if ($includeContacts) {
                $row[] = $people->map(fn (OrgPerson $person): ?string => $person->displayEmail())->filter()->implode(' | ');
                $row[] = $people->map(fn (OrgPerson $person): ?string => $person->displayPhone())->filter()->implode(' | ');
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
            'nodes.*.capacity' => ['nullable', Rule::in(array_keys(OrgNode::CAPACITIES))],
            'nodes.*.access_level' => ['nullable', Rule::in(array_keys(AccessLevels::LEVELS))],
            'nodes.*.category' => ['required', Rule::in(array_keys(OrgNode::CATEGORIES))],
            'nodes.*.title' => ['required', 'string', 'max:255'],
            'nodes.*.subtitle' => ['nullable', 'string', 'max:255'],
            'nodes.*.description' => ['nullable', 'string', 'max:5000'],
            'nodes.*.governorate_slug' => ['nullable', 'string', 'max:255'],
            'nodes.*.scope' => ['nullable', Rule::in(OrgScopes::slugs())],
            // Anciens exports : le perimetre s'appelait « entite ».
            'nodes.*.entity_slug' => ['nullable', 'string', 'max:255'],
            'nodes.*.status' => ['nullable', Rule::in(array_keys(OrgNode::CLUB_STATUSES))],
            'nodes.*.legacy_id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'nodes.*.email' => ['nullable', 'email', 'max:255'],
            'nodes.*.facebook_url' => ['nullable', 'url', 'max:500'],
            'nodes.*.founded_on' => ['nullable', 'date'],
            'nodes.*.charter_signed' => ['nullable', 'boolean'],
            'nodes.*.institution_name' => ['nullable', 'string', 'max:255'],
            'nodes.*.institution_type' => ['nullable', Rule::in(array_keys(OrgNode::INSTITUTION_TYPES))],
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
            'people.*.user_email' => ['nullable', 'string', 'max:255'],
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

        $data['nodes'] = $this->checkedCapacities($data['nodes'], $data['assignments']);

        return DB::transaction(function () use ($data): array {
            // Les adhesions, publications et evenements pointent vers des clubs (blocs) : on memorise leurs liens
            // pour les restaurer sur les nouveaux blocs.
            $links = $this->snapshotNodeLinks();

            OrgAssignment::query()->delete();
            OrgNode::query()->update(['parent_id' => null]);
            OrgNode::query()->delete();
            OrgPerson::query()->delete();

            $personMap = [];

            foreach ($data['people'] as $person) {
                $created = OrgPerson::query()->create([
                    'user_id' => ! empty($person['user_email']) ? User::query()->where('email', $person['user_email'])->value('id') : null,
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
                $governorateId = ! empty($node['governorate_slug'])
                    ? Governorate::query()->where('slug', $node['governorate_slug'])->value('id')
                    : null;
                $scope = $node['scope'] ?? (OrgScopes::valid($node['entity_slug'] ?? null) ? $node['entity_slug'] : null);

                $created = OrgNode::query()->create([
                    'parent_id' => $node['parent_id'] !== null ? $nodeMap[$node['parent_id']] : null,
                    'relation_type' => $node['relation_type'],
                    'kind' => $node['kind'],
                    'capacity' => $node['capacity'],
                    'access_level' => $node['kind'] === 'position' ? ($node['access_level'] ?? 'none') : 'none',
                    'category' => $node['category'],
                    'title' => $node['title'],
                    'subtitle' => $node['subtitle'] ?? null,
                    'description' => $node['description'] ?? null,
                    'governorate_id' => $governorateId,
                    'scope' => $scope,
                    'status' => $node['status'] ?? 'active',
                    'legacy_id' => $node['legacy_id'] ?? null,
                    'email' => $node['email'] ?? null,
                    'facebook_url' => $node['facebook_url'] ?? null,
                    'founded_on' => $node['founded_on'] ?? null,
                    'charter_signed' => (bool) ($node['charter_signed'] ?? false),
                    'institution_name' => $node['institution_name'] ?? null,
                    'institution_type' => $node['institution_type'] ?? null,
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

            // Export antérieur aux niveaux d'accès : on applique les niveaux par défaut plutôt que de retirer tous les droits.
            if (collect($data['nodes'])->contains(fn (array $node): bool => ! array_key_exists('access_level', $node))) {
                app(OrgChartInstaller::class)->applyDefaultAccess();
            }

            $this->restoreNodeLinks($links);

            return [
                'nodes' => count($nodeMap),
                'people' => count($personMap),
                'assignments' => count($data['assignments']),
            ];
        });
    }

    /** Tables dont les lignes pointent vers un club / une unite de l'organigramme. */
    private const LINKED_TABLES = ['memberships', 'posts', 'events'];

    /**
     * Cle stable d'un bloc d'un export a l'autre : identifiant legacy, cle systeme, sinon titre et gouvernorat.
     */
    private function nodeKey(object $node): string
    {
        return match (true) {
            $node->legacy_id !== null => 'legacy:'.$node->legacy_id,
            $node->system_key !== null => 'sys:'.$node->system_key,
            default => 'title:'.Str::lower($node->title).'|'.($node->governorate_id ?? ''),
        };
    }

    /**
     * @return array<string, array<string, array<int, int>>> table => cle de bloc => identifiants de lignes
     */
    private function snapshotNodeLinks(): array
    {
        $keys = DB::table('org_nodes')->get(['id', 'legacy_id', 'system_key', 'title', 'governorate_id'])
            ->mapWithKeys(fn ($node): array => [$node->id => $this->nodeKey($node)])->all();

        $links = [];

        foreach (self::LINKED_TABLES as $table) {
            foreach (DB::table($table)->whereNotNull('node_id')->get(['id', 'node_id']) as $row) {
                if (isset($keys[$row->node_id])) {
                    $links[$table][$keys[$row->node_id]][] = (int) $row->id;
                }
            }
        }

        return $links;
    }

    /**
     * @param array<string, array<string, array<int, int>>> $links
     */
    private function restoreNodeLinks(array $links): void
    {
        if ($links === []) {
            return;
        }

        $ids = DB::table('org_nodes')->get(['id', 'legacy_id', 'system_key', 'title', 'governorate_id'])
            ->mapWithKeys(fn ($node): array => [$this->nodeKey($node) => (int) $node->id])->all();

        foreach ($links as $table => $byKey) {
            foreach ($byKey as $key => $rowIds) {
                if (isset($ids[$key])) {
                    foreach (array_chunk($rowIds, 500) as $chunk) {
                        DB::table($table)->whereIn('id', $chunk)->update(['node_id' => $ids[$key]]);
                    }
                }
            }
        }
    }

    /**
     * Seuls les postes ont des titulaires, et un poste a titulaire unique n'en a qu'un.
     * Les anciens exports sans capacite : un poste qui a plusieurs personnes devient « plusieurs personnes ».
     *
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, array<string, mixed>> $assignments
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException
     */
    private function checkedCapacities(array $nodes, array $assignments): array
    {
        $counts = collect($assignments)->groupBy('node_id')->map(fn ($rows): int => $rows->pluck('person_id')->unique()->count());

        foreach ($nodes as $index => $node) {
            $count = (int) ($counts[$node['id']] ?? 0);
            $capacity = $node['capacity'] ?? ($count > 1 ? 'multiple' : 'single');

            if ($count > 0 && $node['kind'] !== 'position') {
                throw ValidationException::withMessages(['assignments' => 'Le bloc « '.$node['title'].' » n’est pas un poste : seuls les postes ont des titulaires.']);
            }

            if ($capacity === 'single' && $count > 1) {
                throw ValidationException::withMessages(['assignments' => 'Le poste « '.$node['title'].' » est à titulaire unique mais a '.$count.' personnes.']);
            }

            $nodes[$index]['capacity'] = $capacity;
        }

        return $nodes;
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

    private function neutralize(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
