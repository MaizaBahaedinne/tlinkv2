<?php

namespace App\Http\Controllers\Admin;

use App\Models\Club;
use App\Models\Department;
use App\Models\Governorate;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClubController extends AdminController
{
    public const STATUSES = [
        'draft' => 'Brouillon',
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'archived' => 'Archivé',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(self::STATUSES))],
            'entity' => ['nullable', 'integer'],
        ]);

        $clubs = $this->scoped(Club::query(), $request, 'entity.manage')
            ->with(['entity:id,name', 'department:id,name', 'institution:id,name', 'governorate:id,name'])
            ->withCount('memberships')
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->where('name', 'like', "%{$needle}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['entity'] ?? null, fn ($query, $entity) => $query->where('entity_id', $entity))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.clubs.index', [
            'clubs' => $clubs,
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'entities' => $this->entityOptions($request, 'entity.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.clubs.form', $this->formData($request, new Club(['status' => 'active'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $club = Club::query()->create($data + [
            'slug' => $this->uniqueSlug(Club::class, $data['name']),
        ]);

        $this->audit($request, 'admin.club.created', $club, ['name' => $club->name, 'entity_id' => $club->entity_id]);

        return redirect()->route('admin.clubs.index')->with('status', "Club « {$club->name} » créé.");
    }

    public function edit(Request $request, Club $club): View
    {
        $this->authorizeEntity($request, 'entity.manage', $club->entity_id);

        return view('admin.clubs.form', $this->formData($request, $club));
    }

    public function update(Request $request, Club $club): RedirectResponse
    {
        $this->authorizeEntity($request, 'entity.manage', $club->entity_id);

        $club->update($this->validated($request));

        $this->audit($request, 'admin.club.updated', $club, ['name' => $club->name]);

        return redirect()->route('admin.clubs.index')->with('status', "Club « {$club->name} » mis à jour.");
    }

    public function destroy(Request $request, Club $club): RedirectResponse
    {
        $this->authorizeEntity($request, 'entity.manage', $club->entity_id);

        if ($club->memberships()->exists()) {
            return back()->with('error', 'Suppression impossible : ce club possède des adhésions. Archivez-le plutôt.');
        }

        $name = $club->name;
        $this->audit($request, 'admin.club.deleted', $club, ['name' => $name]);
        $club->delete();

        return redirect()->route('admin.clubs.index')->with('status', "Club « {$name} » supprimé.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, Club $club): array
    {
        $scope = $this->context($request)->scope('entity.manage') ?? [];

        $departments = Department::query()
            ->when($scope !== [], fn ($query) => $query->whereIn('entity_id', $scope))
            ->orderBy('name')
            ->get(['id', 'name', 'entity_id']);

        $institutions = Institution::query()
            ->when($scope !== [], fn ($query) => $query->whereIn('entity_id', $scope))
            ->orderBy('name')
            ->get(['id', 'name', 'entity_id']);

        return [
            'club' => $club,
            'statuses' => self::STATUSES,
            'canBeGlobal' => $scope === [],
            'entities' => $this->entityOptions($request, 'entity.manage'),
            'departments' => $departments->pluck('name', 'id')->all(),
            'departmentParents' => $departments->pluck('entity_id', 'id')->all(),
            'institutions' => $institutions->pluck('name', 'id')->all(),
            'institutionParents' => $institutions->pluck('entity_id', 'id')->all(),
            'governorates' => Governorate::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $scope = $this->context($request)->scope('entity.manage');
        $allowedEntities = array_keys($this->entityOptions($request, 'entity.manage'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'entity_id' => [$scope === [] ? 'nullable' : 'required', 'integer', Rule::in($allowedEntities)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ]);

        $errors = [];

        if (! empty($data['department_id']) && ! empty($data['entity_id'])) {
            if ((int) Department::query()->whereKey($data['department_id'])->value('entity_id') !== (int) $data['entity_id']) {
                $errors['department_id'] = 'Ce département n\'appartient pas à l\'entité sélectionnée.';
            }
        }

        if (! empty($data['institution_id']) && ! empty($data['entity_id'])) {
            $institutionEntity = Institution::query()->whereKey($data['institution_id'])->value('entity_id');
            if ($institutionEntity !== null && (int) $institutionEntity !== (int) $data['entity_id']) {
                $errors['institution_id'] = 'Cette institution n\'appartient pas à l\'entité sélectionnée.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}
