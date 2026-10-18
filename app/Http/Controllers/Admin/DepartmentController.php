<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends AdminController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'entity' => ['nullable', 'integer'],
        ]);

        $departments = $this->scoped(Department::query(), $request, 'entity.manage')
            ->with('entity:id,name')
            ->withCount(['clubs', 'memberships'])
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->where('name', 'like', "%{$needle}%"))
            ->when($filters['entity'] ?? null, fn ($query, $entity) => $query->where('entity_id', $entity))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.departments.index', [
            'departments' => $departments,
            'filters' => $filters,
            'entities' => $this->entityOptions($request, 'entity.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.departments.form', [
            'department' => new Department(['entity_id' => $request->integer('entity') ?: null]),
            'entities' => $this->entityOptions($request, 'entity.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $department = Department::query()->create($data + [
            'slug' => $this->uniqueSlug(Department::class, $data['name'], ['entity_id' => $data['entity_id']]),
        ]);

        $this->audit($request, 'admin.department.created', $department, ['name' => $department->name, 'entity_id' => $department->entity_id]);

        return redirect()->route('admin.departments.index')->with('status', "Département « {$department->name} » créé.");
    }

    public function edit(Request $request, Department $department): View
    {
        $this->authorizeEntity($request, 'entity.manage', $department->entity_id);

        return view('admin.departments.form', [
            'department' => $department,
            'entities' => $this->entityOptions($request, 'entity.manage'),
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeEntity($request, 'entity.manage', $department->entity_id);

        $data = $this->validated($request);

        if ((int) $data['entity_id'] !== (int) $department->entity_id) {
            $data['slug'] = $this->uniqueSlug(Department::class, $data['name'], ['entity_id' => $data['entity_id']], $department->id);
        }

        $department->update($data);

        $this->audit($request, 'admin.department.updated', $department, ['name' => $department->name]);

        return redirect()->route('admin.departments.index')->with('status', "Département « {$department->name} » mis à jour.");
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeEntity($request, 'entity.manage', $department->entity_id);

        $department->loadCount(['clubs', 'memberships']);

        if ($department->clubs_count + $department->memberships_count > 0) {
            return back()->with('error', 'Suppression impossible : ce département est encore rattaché à des clubs ou des adhésions.');
        }

        $name = $department->name;
        $this->audit($request, 'admin.department.deleted', $department, ['name' => $name]);
        $department->delete();

        return redirect()->route('admin.departments.index')->with('status', "Département « {$name} » supprimé.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $allowedEntities = array_keys($this->entityOptions($request, 'entity.manage'));

        $data = $request->validate([
            'entity_id' => ['required', 'integer', Rule::in($allowedEntities)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['description'] = isset($data['description']) ? trim($data['description']) : null;

        return $data;
    }
}
