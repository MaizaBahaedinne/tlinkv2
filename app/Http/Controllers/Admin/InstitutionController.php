<?php

namespace App\Http\Controllers\Admin;

use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstitutionController extends AdminController
{
    public const TYPES = [
        'university' => 'Université',
        'high_school' => 'Lycée',
        'other' => 'Autre',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:'.implode(',', array_keys(self::TYPES))],
            'entity' => ['nullable', 'integer'],
        ]);

        $institutions = $this->scoped(Institution::query(), $request, 'entity.manage')
            ->with('entity:id,name')
            ->withCount('clubs')
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->where('name', 'like', "%{$needle}%"))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('institution_type', $type))
            ->when($filters['entity'] ?? null, fn ($query, $entity) => $query->where('entity_id', $entity))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.institutions.index', [
            'institutions' => $institutions,
            'filters' => $filters,
            'types' => self::TYPES,
            'entities' => $this->entityOptions($request, 'entity.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.institutions.form', [
            'institution' => new Institution(['is_active' => true, 'entity_id' => $request->integer('entity') ?: null]),
            'types' => self::TYPES,
            'entities' => $this->entityOptions($request, 'entity.manage'),
            'canBeGlobal' => $this->context($request)->isGlobal('entity.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $institution = Institution::query()->create($data + [
            'slug' => $this->uniqueSlug(Institution::class, $data['name'], ['institution_type' => $data['institution_type']]),
        ]);

        $this->audit($request, 'admin.institution.created', $institution, ['name' => $institution->name, 'type' => $institution->institution_type]);

        return redirect()->route('admin.institutions.index')->with('status', "Institution « {$institution->name} » créée.");
    }

    public function edit(Request $request, Institution $institution): View
    {
        $this->authorizeEntity($request, 'entity.manage', $institution->entity_id);

        return view('admin.institutions.form', [
            'institution' => $institution,
            'types' => self::TYPES,
            'entities' => $this->entityOptions($request, 'entity.manage'),
            'canBeGlobal' => $this->context($request)->isGlobal('entity.manage'),
        ]);
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeEntity($request, 'entity.manage', $institution->entity_id);

        $data = $this->validated($request);

        if ($data['institution_type'] !== $institution->institution_type) {
            $data['slug'] = $this->uniqueSlug(Institution::class, $data['name'], ['institution_type' => $data['institution_type']], $institution->id);
        }

        $institution->update($data);

        $this->audit($request, 'admin.institution.updated', $institution, ['name' => $institution->name]);

        return redirect()->route('admin.institutions.index')->with('status', "Institution « {$institution->name} » mise à jour.");
    }

    public function destroy(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeEntity($request, 'entity.manage', $institution->entity_id);

        if ($institution->clubs()->exists()) {
            return back()->with('error', 'Suppression impossible : cette institution possède encore des clubs.');
        }

        $name = $institution->name;
        $this->audit($request, 'admin.institution.deleted', $institution, ['name' => $name]);
        $institution->delete();

        return redirect()->route('admin.institutions.index')->with('status', "Institution « {$name} » supprimée.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $scope = $this->context($request)->scope('entity.manage');
        $allowedEntities = array_keys($this->entityOptions($request, 'entity.manage'));

        $data = $request->validate([
            // Un administrateur limite a certaines entites doit rattacher l'institution a l'une d'elles.
            'entity_id' => [$scope === [] ? 'nullable' : 'required', 'integer', Rule::in($allowedEntities)],
            'name' => ['required', 'string', 'max:120'],
            'institution_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['description'] = isset($data['description']) ? trim($data['description']) : null;
        $data['is_active'] = (bool) $data['is_active'];

        return $data;
    }
}
