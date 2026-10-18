<?php

namespace App\Http\Controllers\Admin;

use App\Models\Entity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntityController extends AdminController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $entities = $this->scoped(Entity::query(), $request, 'entity.manage', 'id')
            ->withCount(['departments', 'institutions', 'clubs', 'memberships'])
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $query->where(fn ($inner) => $inner->where('name', 'like', "%{$needle}%")->orWhere('category', 'like', "%{$needle}%"));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.entities.index', [
            'entities' => $entities,
            'filters' => $filters,
            'canCreate' => $this->context($request)->isGlobal('entity.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->requireGlobal($request);

        return view('admin.entities.form', ['entity' => new Entity(['is_active' => true]), 'categories' => $this->categories()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireGlobal($request);

        $data = $this->validated($request);

        $entity = Entity::query()->create($data + [
            'slug' => $this->uniqueSlug(Entity::class, $data['name']),
        ]);

        $this->audit($request, 'admin.entity.created', $entity, ['name' => $entity->name]);

        return redirect()->route('admin.entities.index')->with('status', "Entité « {$entity->name} » créée.");
    }

    public function edit(Request $request, Entity $entity): View
    {
        $this->authorizeEntity($request, 'entity.manage', $entity->id);

        return view('admin.entities.form', ['entity' => $entity, 'categories' => $this->categories()]);
    }

    public function update(Request $request, Entity $entity): RedirectResponse
    {
        $this->authorizeEntity($request, 'entity.manage', $entity->id);

        $entity->update($this->validated($request, $entity));

        $this->audit($request, 'admin.entity.updated', $entity, ['name' => $entity->name]);

        return redirect()->route('admin.entities.index')->with('status', "Entité « {$entity->name} » mise à jour.");
    }

    public function destroy(Request $request, Entity $entity): RedirectResponse
    {
        $this->requireGlobal($request);

        $entity->loadCount(['departments', 'institutions', 'clubs', 'memberships']);

        if ($entity->departments_count + $entity->institutions_count + $entity->clubs_count + $entity->memberships_count > 0) {
            return back()->with('error', 'Suppression impossible : cette entité contient encore des départements, institutions, clubs ou adhésions. Désactivez-la plutôt.');
        }

        $name = $entity->name;
        $this->audit($request, 'admin.entity.deleted', $entity, ['name' => $name]);
        $entity->delete();

        return redirect()->route('admin.entities.index')->with('status', "Entité « {$name} » supprimée.");
    }

    private function requireGlobal(Request $request): void
    {
        abort_unless(
            $this->context($request)->isGlobal('entity.manage'),
            403,
            'Seuls les administrateurs globaux peuvent créer ou supprimer une entité.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Entity $entity = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('entities', 'name')->ignore($entity?->id)],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['description'] = isset($data['description']) ? trim($data['description']) : null;
        $data['is_active'] = (bool) $data['is_active'];

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function categories(): array
    {
        return Entity::query()->distinct()->orderBy('category')->pluck('category')->all();
    }
}
