<?php

namespace App\Http\Controllers\Admin;

use App\Models\ClubTemplateSlot;
use App\Models\OrgNode;
use App\Support\AccessLevels;
use App\Support\AdminContext;
use App\Support\AuditLogger;
use App\Support\OrgChart\ClubTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Modèles d'équipe des clubs, un par type (University, High School, We Are Carthage) : intitulés des postes,
 * départements, capacités et niveaux d'accès. Tous les clubs du type suivent le modèle dès l'enregistrement.
 * L'écran est un organigramme interactif (même moteur que /organigramme) ; ses actions répondent en JSON.
 * Lecture pour qui pilote l'organisation, écriture réservée aux administrateurs globaux.
 */
class ClubTemplateController extends AdminController
{
    public function index(Request $request, string $category): View
    {
        return view('admin.structure.templates', [
            'canEdit' => $this->canEdit($request),
            'category' => $category,
            'categories' => ClubTemplates::CATEGORIES,
            'payload' => $this->payload($category, $this->canEdit($request)),
            'userName' => $request->user()->name,
            'adminUrl' => route('admin.clubs.index'),
        ]);
    }

    /** Remplace tout le modèle d'un coup (formulaire classique). */
    public function update(Request $request, string $category): RedirectResponse|JsonResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'slots' => ['required', 'array'],
            'slots.*.title' => ['required', 'string', 'max:120'],
            'slots.*.capacity' => ['required', Rule::in(['single', 'multiple'])],
            'slots.*.access_level' => ['required', Rule::in(ClubTemplates::ALLOWED_LEVELS)],
            'slots.*.parent_key' => ['nullable', 'string', 'max:40'],
        ]);

        $changes = $this->applyRows($request, $category, $data['slots']);

        return $this->respond($request, $category, 'Modèle enregistré : '.$changes.' bloc(s) mis à jour dans les clubs '.ClubTemplates::CATEGORIES[$category].'.');
    }

    /** Modifie un seul bloc : intitulé, rattachement, titulaires, niveau d'accès. */
    public function updateSlot(Request $request, string $category, string $key): RedirectResponse|JsonResponse
    {
        $this->requireEditor($request);

        $slot = ClubTemplateSlot::query()->where('category', $category)->where('key', $key)->firstOrFail();

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'parent_key' => ['sometimes', 'nullable', 'string', 'max:40'],
            'capacity' => ['sometimes', Rule::in(['single', 'multiple'])],
            'access_level' => ['sometimes', Rule::in(ClubTemplates::ALLOWED_LEVELS)],
        ]);

        $row = array_merge([
            'title' => $slot->title, 'parent_key' => $slot->parent_key, 'capacity' => $slot->capacity, 'access_level' => $slot->access_level,
        ], $data);

        $changes = $this->applyRows($request, $category, [$key => $row]);

        return $this->respond($request, $category, '« '.$row['title'].' » enregistré : '.$changes.' bloc(s) mis à jour dans les clubs.');
    }

    public function store(Request $request, string $category): RedirectResponse|JsonResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(array_keys(ClubTemplates::KINDS))],
            'parent_key' => ['nullable', 'string', 'max:40'],
            'capacity' => ['nullable', Rule::in(['single', 'multiple'])],
            'access_level' => ['nullable', Rule::in(ClubTemplates::ALLOWED_LEVELS)],
        ]);

        $existing = ClubTemplateSlot::query()->where('category', $category)->get()->keyBy('key');
        $parent = $data['parent_key'] ?? null;

        if ($parent !== null && $parent !== '' && ! $existing->has($parent)) {
            throw ValidationException::withMessages(['parent_key' => 'Rattachement inconnu.']);
        }

        $this->assertDistinctTitles($existing, [], trim($data['title']));
        $isPosition = $data['kind'] === 'position';

        $slot = ClubTemplateSlot::query()->create([
            'category' => $category,
            'key' => ClubTemplates::newKey($category, $data['title']),
            'parent_key' => $parent === '' ? null : $parent,
            'kind' => $data['kind'],
            'title' => trim($data['title']),
            'capacity' => $isPosition ? ($data['capacity'] ?? 'single') : 'single',
            'access_level' => $isPosition ? ($data['access_level'] ?? 'none') : 'none',
            'sort_order' => ((int) $existing->max('sort_order')) + 1,
        ]);

        $changes = (new ClubTemplates())->sync($category);
        $this->audit($request, 'orgchart.club_template.slot_added', $slot, ['category' => $category, 'changes' => $changes]);

        return $this->respond($request, $category, '« '.$slot->title.' » ajouté : '.$changes.' bloc(s) créé(s) ou mis à jour dans les clubs.', ['created' => $slot->key]);
    }

    /** Monte ou descend un bloc parmi ses frères. */
    public function move(Request $request, string $category, string $key): RedirectResponse|JsonResponse
    {
        $this->requireEditor($request);

        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $slot = ClubTemplateSlot::query()->where('category', $category)->where('key', $key)->firstOrFail();

        $siblings = ClubTemplateSlot::query()->where('category', $category)->where('parent_key', $slot->parent_key)
            ->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $siblings->search(fn (ClubTemplateSlot $row): bool => $row->id === $slot->id);
        $target = $data['direction'] === 'up' ? $index - 1 : $index + 1;

        if ($target >= 0 && $target < $siblings->count()) {
            $order = $siblings->all();
            [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

            // Seul l'ordre relatif des frères compte : on les renumérote à partir du plus petit rang.
            $first = (int) $siblings->min('sort_order');

            foreach ($order as $position => $row) {
                $row->update(['sort_order' => $first + $position]);
            }

            (new ClubTemplates())->sync($category);
        }

        return $this->respond($request, $category, 'Ordre modifié.');
    }

    /** Réapplique le modèle à tous les clubs du type (utile après un import ou une édition à la main). */
    public function apply(Request $request, string $category): RedirectResponse|JsonResponse
    {
        $this->requireEditor($request);

        $changes = (new ClubTemplates())->sync($category);
        AuditLogger::record($request->user()->id, 'orgchart.club_template.updated', null, null, ['category' => $category, 'changes' => $changes], $request->ip());

        return $this->respond($request, $category, $changes === 0
            ? 'Tous les clubs suivent déjà ce modèle.'
            : $changes.' bloc(s) créé(s), modifié(s) ou retiré(s) pour suivre le modèle.');
    }

    public function destroy(Request $request, string $category, string $key): RedirectResponse|JsonResponse
    {
        $this->requireEditor($request);

        $slot = ClubTemplateSlot::query()->where('category', $category)->where('key', $key)->firstOrFail();

        if (ClubTemplateSlot::query()->where('category', $category)->where('parent_key', $key)->exists()) {
            throw ValidationException::withMessages(['slot' => 'Retirez d’abord les sous-blocs de « '.$slot->title.' » (ou rattachez-les ailleurs).']);
        }

        $title = $slot->title;
        $slot->delete();

        $changes = (new ClubTemplates())->sync($category);
        AuditLogger::record($request->user()->id, 'orgchart.club_template.slot_removed', null, null, ['category' => $category, 'key' => $key, 'title' => $title, 'changes' => $changes], $request->ip());

        return $this->respond($request, $category, '« '.$title.' » retiré du modèle. Les blocs vides sont supprimés des clubs ; ceux qui ont des titulaires sont conservés, sans rôle.');
    }

    /**
     * Applique des modifications de blocs (clé => intitulé, rattachement, capacité, niveau) puis aligne les clubs.
     *
     * @param array<string, array<string, mixed>> $submitted
     */
    private function applyRows(Request $request, string $category, array $submitted): int
    {
        $existing = ClubTemplateSlot::query()->where('category', $category)->get()->keyBy('key');
        $rows = array_intersect_key($submitted, $existing->all());
        $parentOf = $existing->map(fn (ClubTemplateSlot $slot): ?string => $slot->parent_key)->all();

        foreach ($rows as $key => $row) {
            $parent = $row['parent_key'] ?? null;
            $parent = $parent === '' ? null : $parent;

            if ($parent !== null && ! $existing->has($parent)) {
                throw ValidationException::withMessages(['parent_key' => 'Rattachement inconnu.']);
            }

            $rows[$key]['parent_key'] = $parent;
            $parentOf[$key] = $parent;
        }

        foreach (array_keys($rows) as $key) {
            for ($walk = $parentOf[$key] ?? null, $guard = 0; $walk !== null; $walk = $parentOf[$walk] ?? null, $guard++) {
                if ($walk === $key || $guard > 50) {
                    throw ValidationException::withMessages(['parent_key' => 'Un bloc ne peut pas être rattaché à l’un de ses propres sous-blocs.']);
                }
            }
        }

        $this->assertDistinctTitles($existing, $rows);

        foreach ($rows as $key => $row) {
            $slot = $existing[$key];
            $isPosition = $slot->kind === 'position';

            $slot->update([
                'title' => trim((string) $row['title']),
                'parent_key' => $row['parent_key'],
                'capacity' => $isPosition ? $row['capacity'] : 'single',
                'access_level' => $isPosition ? $row['access_level'] : 'none',
            ]);
        }

        $changes = (new ClubTemplates())->sync($category);
        AuditLogger::record($request->user()->id, 'orgchart.club_template.updated', null, null, ['category' => $category, 'slots' => count($rows), 'changes' => $changes], $request->ip());

        return $changes;
    }

    /**
     * Données de l'organigramme du modèle : un bloc « club » racine et les blocs du modèle (ordre = ordre de l'arbre).
     *
     * @return array<string, mixed>
     */
    private function payload(string $category, bool $canEdit): array
    {
        $slots = ClubTemplates::slots($category);
        $ids = collect($slots)->map(fn (array $slot): ?int => $slot['id']);
        $counts = OrgNode::query()->where('category', $category)->whereNotNull('role_key')
            ->selectRaw('role_key, count(*) as total')->groupBy('role_key')->pluck('total', 'role_key')->all();

        $blocks = [];
        $order = 0;

        foreach ($slots as $key => $slot) {
            $blocks[] = [
                'id' => $slot['id'],
                'key' => $key,
                'parent_id' => $slot['parent'] !== null ? $ids[$slot['parent']] : null,
                'parent_key' => $slot['parent'],
                'title' => $slot['title'],
                'kind' => $slot['kind'],
                'capacity' => $slot['capacity'],
                'access_level' => $slot['access'],
                'sort_order' => $order++,
                'clubs' => (int) ($counts[$key] ?? 0),
            ];
        }

        return [
            'category' => $category,
            'label' => ClubTemplates::CATEGORIES[$category],
            'can_edit' => $canEdit,
            'club_count' => OrgNode::query()->where('kind', 'club')->where('category', $category)->count(),
            'blocks' => $blocks,
            'kinds' => ClubTemplates::KINDS,
            'levels' => array_intersect_key(AccessLevels::LEVELS, array_flip(ClubTemplates::ALLOWED_LEVELS)),
            'level_permissions' => collect(array_flip(ClubTemplates::ALLOWED_LEVELS))->map(fn ($i, $level): array => array_map(
                fn (string $permission): string => AccessLevels::PERMISSIONS[$permission],
                AccessLevels::permissions($level)
            ))->all(),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function respond(Request $request, string $category, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + $extra + ['chart' => $this->payload($category, $this->canEdit($request))]);
        }

        return back()->with('status', $message);
    }

    /**
     * Deux blocs du même type ne portent pas le même intitulé : c'est lui qui rattache un bloc de club à son rôle.
     *
     * @param Collection<string, ClubTemplateSlot> $existing
     * @param array<string, array<string, mixed>> $rows
     */
    private function assertDistinctTitles(Collection $existing, array $rows, ?string $added = null): void
    {
        $seen = [];

        foreach ($existing as $key => $slot) {
            $title = isset($rows[$key]) ? trim((string) $rows[$key]['title']) : $slot->title;
            $seen[$slot->kind.'|'.mb_strtolower($title)][] = $key;
        }

        foreach ($seen as $keys) {
            if (count($keys) > 1) {
                throw ValidationException::withMessages(['title' => 'Cet intitulé existe déjà dans le modèle.']);
            }
        }

        if ($added !== null) {
            foreach ($existing as $slot) {
                if (mb_strtolower($slot->title) === mb_strtolower($added)) {
                    throw ValidationException::withMessages(['title' => 'Cet intitulé existe déjà dans le modèle.']);
                }
            }
        }
    }

    private function canEdit(Request $request): bool
    {
        return AdminContext::of($request)->isGlobal('entity.manage');
    }

    private function requireEditor(Request $request): void
    {
        abort_unless($this->canEdit($request), 403, 'Seuls les administrateurs globaux peuvent modifier les modèles de clubs.');
    }
}
