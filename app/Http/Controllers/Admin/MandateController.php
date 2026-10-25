<?php

namespace App\Http\Controllers\Admin;

use App\Models\Mandate;
use App\Models\OrgNode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MandateController extends AdminController
{
    public function index(): View
    {
        $this->bootstrapDefaults();

        return view('admin.mandates.index', [
            'mandates' => Mandate::query()->orderByDesc('start_year')->orderByDesc('id')->get(),
            'current' => Mandate::query()->where('is_current', true)->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['is_current']) {
            Mandate::query()->update(['is_current' => false]);
        }

        $root = $this->createMandateRootNode($data['bureau_name'], $data['label']);
        $data['root_node_id'] = $root->id;

        $mandate = Mandate::query()->create($data);

        $this->audit($request, 'admin.mandate.created', $mandate, [
            'label' => $mandate->label,
            'bureau_name' => $mandate->bureau_name,
            'is_current' => $mandate->is_current,
        ]);

        return back()->with('status', 'Mandat ajouté.');
    }

    public function update(Request $request, Mandate $mandate): RedirectResponse
    {
        $data = $this->validated($request, $mandate->id);

        if ($data['is_current']) {
            Mandate::query()->whereKeyNot($mandate->id)->update(['is_current' => false]);
        }

        if ($mandate->root_node_id) {
            OrgNode::query()->whereKey($mandate->root_node_id)->update([
                'title' => $data['bureau_name'],
                'subtitle' => 'Mandat '.$data['label'],
            ]);
        } else {
            $root = $this->createMandateRootNode($data['bureau_name'], $data['label']);
            $data['root_node_id'] = $root->id;
        }

        $mandate->update($data);

        $this->audit($request, 'admin.mandate.updated', $mandate, [
            'label' => $mandate->label,
            'bureau_name' => $mandate->bureau_name,
            'is_current' => $mandate->is_current,
        ]);

        return back()->with('status', 'Mandat mis à jour.');
    }

    public function destroy(Request $request, Mandate $mandate): RedirectResponse
    {
        $snapshot = [
            'label' => $mandate->label,
            'bureau_name' => $mandate->bureau_name,
            'root_node_id' => $mandate->root_node_id,
            'is_current' => $mandate->is_current,
        ];

        $mandate->delete();

        $this->audit($request, 'admin.mandate.deleted', $mandate, $snapshot);

        return back()->with('status', 'Mandat supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $min = 2016;
        $max = (int) now()->year + 1;

        $data = $request->validate([
            'start_year' => ['required', 'integer', 'min:'.$min, 'max:'.$max],
            'end_year' => ['required', 'integer', 'min:'.($min + 1), 'max:'.($max + 1)],
            'bureau_name' => ['required', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        if ((int) $data['end_year'] <= (int) $data['start_year']) {
            return back()->withErrors(['end_year' => 'L’année de fin doit être supérieure à l’année de début.'])->throwResponse();
        }

        if ((int) $data['end_year'] > (int) $data['start_year'] + 1) {
            return back()->withErrors(['end_year' => 'Un mandat couvre une seule période annuelle (exemple: 2016-2017).'])->throwResponse();
        }

        $label = $data['start_year'].'-'.$data['end_year'];

        $duplicate = Mandate::query()
            ->where('label', $label)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['start_year' => 'Ce mandat existe déjà.'])->throwResponse();
        }

        return [
            'label' => $label,
            'start_year' => (int) $data['start_year'],
            'end_year' => (int) $data['end_year'],
            'bureau_name' => trim((string) $data['bureau_name']),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'is_current' => (bool) ($data['is_current'] ?? false),
        ];
    }

    private function bootstrapDefaults(): void
    {
        if (Mandate::query()->exists()) {
            return;
        }

        $currentYear = (int) now()->year;

        // Le mandat en cours reprend l'organigramme existant (racine historique).
        $legacyRootId = OrgNode::query()->whereNull('parent_id')->orderBy('id')->value('id');

        for ($year = 2016; $year <= $currentYear; $year++) {
            $label = $year.'-'.($year + 1);
            $bureau = 'Bureau Tunivisions';
            $rootId = ($year === $currentYear && $legacyRootId)
                ? $legacyRootId
                : $this->createMandateRootNode($bureau, $label)->id;

            Mandate::query()->create([
                'label' => $label,
                'start_year' => $year,
                'end_year' => $year + 1,
                'bureau_name' => $bureau,
                'root_node_id' => $rootId,
                'notes' => '',
                'is_current' => $year === $currentYear,
            ]);
        }
    }

    private function createMandateRootNode(string $bureauName, string $label): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => null,
            'relation_type' => 'hierarchy',
            'kind' => 'unit',
            'capacity' => 'single',
            'access_level' => 'none',
            'category' => 'direction',
            'title' => $bureauName,
            'subtitle' => 'Mandat '.$label,
            'description' => 'Organigramme du mandat '.$label,
            'sort_order' => 0,
            'scope' => 'foundation',
            'validation_status' => 'confirmed',
            'children_layout' => 'horizontal',
            'is_collapsed' => false,
        ]);
    }
}
