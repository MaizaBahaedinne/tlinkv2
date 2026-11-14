<?php

namespace App\Http\Controllers\Admin;

use App\Models\ClubTemplateSlot;
use App\Models\OrgNode;
use App\Support\AccessLevels;
use App\Support\AdminContext;
use App\Support\AuditLogger;
use App\Support\OrgChart\ClubTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Modèles d'équipe des clubs, un par type (University, High School, We Are Carthage) : intitulés des postes,
 * départements, capacités et niveaux d'accès. Tous les clubs du type suivent le modèle dès l'enregistrement.
 * Lecture pour qui pilote l'organisation, écriture réservée aux administrateurs globaux.
 */
class ClubTemplateController extends AdminController
{
    public function index(Request $request, string $category): View
    {
        $slots = ClubTemplates::slots($category);

        $counts = OrgNode::query()->where('category', $category)->whereNotNull('role_key')
            ->selectRaw('role_key, count(*) as total')->groupBy('role_key')->pluck('total', 'role_key')->all();

        $parents = [];

        foreach ($slots as $key => $slot) {
            $excluded = array_merge([$key], ClubTemplates::descendantKeys($category, $key));
            $parents[$key] = collect($slots)->except($excluded)->map(fn (array $other): string => $other['title'])->all();
        }

        return view('admin.structure.templates', [
            'canEdit' => $this->canEdit($request),
            'category' => $category,
            'categories' => ClubTemplates::CATEGORIES,
            'slots' => $slots,
            'parents' => $parents,
            'counts' => $counts,
            'clubCount' => OrgNode::query()->where('kind', 'club')->where('category', $category)->count(),
            'kinds' => ClubTemplates::KINDS,
            'levels' => array_intersect_key(AccessLevels::LEVELS, array_flip(ClubTemplates::ALLOWED_LEVELS)),
            'levelPermissions' => collect(AccessLevels::LEVELS)->map(fn ($label, $level): array => array_map(
                fn (string $permission): string => AccessLevels::PERMISSIONS[$permission],
                AccessLevels::permissions($level)
            ))->all(),
        ]);
    }

    public function update(Request $request, string $category): RedirectResponse
    {
        $this->requireEditor($request);

        $data = $request->validate([
            'slots' => ['required', 'array'],
            'slots.*.title' => ['required', 'string', 'max:120'],
            'slots.*.capacity' => ['required', Rule::in(['single', 'multiple'])],
            'slots.*.access_level' => ['required', Rule::in(ClubTemplates::ALLOWED_LEVELS)],
            'slots.*.parent_key' => ['nullable', 'string', 'max:40'],
        ]);

        $existing = ClubTemplateSlot::query()->where('category', $category)->get()->keyBy('key');
        $rows = array_intersect_key($data['slots'], $existing->all());
        $parentOf = $existing->map(fn (ClubTemplateSlot $slot): ?string => $slot->parent_key)->all();

        foreach ($rows as $key => $row) {
            $parent = $row['parent_key'] ?? null;

            if ($parent !== null && $parent !== '' && ! $existing->has($parent)) {
                throw ValidationException::withMessages(["slots.$key.parent_key" => 'Rattachement inconnu.']);
            }

            $parentOf[$key] = $parent === '' ? null : $parent;
        }

        foreach (array_keys($rows) as $key) {
            for ($walk = $parentOf[$key] ?? null, $guard = 0; $walk !== null; $walk = $parentOf[$walk] ?? null, $guard++) {
                if ($walk === $key || $guard > 50) {
                    throw ValidationException::withMessages(["slots.$key.parent_key" => 'Un bloc ne peut pas être rattaché à l’un de ses propres sous-blocs.']);
                }
            }
        }

        $this->assertDistinctTitles($existing, $rows);

        foreach ($rows as $key => $row) {
            $slot = $existing[$key];
            $isPosition = $slot->kind === 'position';

            $slot->update([
                'title' => trim($row['title']),
                'parent_key' => $parentOf[$key],
                'capacity' => $isPosition ? $row['capacity'] : 'single',
                'access_level' => $isPosition ? $row['access_level'] : 'none',
            ]);
        }

        $changes = (new ClubTemplates())->sync($category);
        AuditLogger::record($request->user()->id, 'orgchart.club_template.updated', null, null, ['category' => $category, 'slots' => count($rows), 'changes' => $changes], $request->ip());

        return back()->with('status', 'Modèle enregistré : '.$changes.' bloc(s) mis à jour dans les clubs '.ClubTemplates::CATEGORIES[$category].'.');
    }

    public function store(Request $request, string $category): RedirectResponse
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

        return back()->with('status', '« '.$slot->title.' » ajouté : '.$changes.' bloc(s) créé(s) ou mis à jour dans les clubs.');
    }

    public function destroy(Request $request, string $category, string $key): RedirectResponse
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

        return back()->with('status', '« '.$title.' » retiré du modèle. Les blocs vides sont supprimés des clubs ; ceux qui ont des titulaires sont conservés, sans rôle.');
    }

    /**
     * Deux blocs du même type ne portent pas le même intitulé : c'est lui qui rattache un bloc de club à son rôle.
     *
     * @param \Illuminate\Support\Collection<string, ClubTemplateSlot> $existing
     * @param array<string, array<string, mixed>> $rows
     */
    private function assertDistinctTitles($existing, array $rows, ?string $added = null): void
    {
        $seen = [];

        foreach ($existing as $key => $slot) {
            $title = isset($rows[$key]) ? trim((string) $rows[$key]['title']) : $slot->title;
            $seen[$slot->kind.'|'.mb_strtolower($title)][] = $key;
        }

        foreach ($seen as $keys) {
            if (count($keys) > 1) {
                throw ValidationException::withMessages(["slots.{$keys[1]}.title" => 'Cet intitulé existe déjà dans le modèle.']);
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
