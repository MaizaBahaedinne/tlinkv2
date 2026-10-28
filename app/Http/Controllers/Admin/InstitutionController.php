<?php

namespace App\Http\Controllers\Admin;

use App\Models\Governorate;
use App\Models\Institution;
use App\Support\ProfileOrganizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Établissements d'enseignement (lycées, universités, facultés, écoles) : chaque club d'université ou de lycée
 * y est rattaché, et les profils des membres affichent leur logo et leurs informations.
 */
class InstitutionController extends AdminController
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $onlyMissing = $request->boolean('sans_logo');

        $institutions = Institution::query()
            ->with(['parent:id,name', 'governorate:id,name', 'clubs:id,title,institution_id'])
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '\\%_').'%'))
            ->when(array_key_exists($type, Institution::TYPES), fn ($query) => $query->where('type', $type))
            ->when($onlyMissing, fn ($query) => $query->whereNull('logo_path'))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.institutions.index', [
            'institutions' => $institutions,
            'term' => $term,
            'type' => $type,
            'onlyMissing' => $onlyMissing,
            'withoutLogo' => Institution::query()->whereNull('logo_path')->count(),
            'universities' => Institution::query()->where('type', 'university')->orderBy('name')->get(['id', 'name']),
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = ProfileOrganizations::key($data['name']);

        if (Institution::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Cet établissement existe déjà.']);
        }

        $institution = Institution::query()->create($this->values($data) + [
            'slug' => $slug,
            'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('institutions/logos', 'public') : null,
        ]);

        $this->audit($request, 'admin.institution.created', $institution, ['name' => $institution->name]);

        return back()->with('status', 'Établissement ajouté.');
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        $data = $this->validated($request, $institution);
        $slug = ProfileOrganizations::key($data['name']);

        if (Institution::query()->where('slug', $slug)->whereKeyNot($institution->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Un autre établissement porte déjà ce nom.']);
        }

        $values = $this->values($data) + ['slug' => $slug];
        $oldLogo = null;

        if ($request->hasFile('logo')) {
            $oldLogo = $institution->logo_path;
            $values['logo_path'] = $request->file('logo')->store('institutions/logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $oldLogo = $institution->logo_path;
            $values['logo_path'] = null;
        }

        $institution->update($values);
        $this->deleteLogo($oldLogo);

        $this->audit($request, 'admin.institution.updated', $institution, ['name' => $institution->name]);

        return back()->with('status', 'Établissement modifié.');
    }

    public function destroy(Request $request, Institution $institution): RedirectResponse
    {
        if ($institution->clubs()->exists()) {
            return back()->withErrors(['institution' => 'Cet établissement a des clubs rattachés : détachez-les avant de le supprimer.']);
        }

        $this->audit($request, 'admin.institution.deleted', $institution, ['name' => $institution->name]);

        $logo = $institution->logo_path;
        $institution->delete();
        $this->deleteLogo($logo);

        return back()->with('status', 'Établissement supprimé : les profils gardent le nom saisi, sans logo.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Institution $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(Institution::TYPES))],
            'parent_id' => ['nullable', 'integer', Rule::exists('institutions', 'id')->where('type', 'university')],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_logo' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'logo.uploaded' => 'Le logo n’a pas pu être envoyé : le fichier dépasse la taille acceptée par le serveur.',
            'logo.max' => 'Le logo est trop lourd (3 Mo maximum).',
            'logo.image' => 'Le logo doit être une image (jpg, png ou webp).',
            'logo.mimes' => 'Le logo doit être au format jpg, png ou webp.',
        ]);

        $data['name'] = trim(preg_replace('/\s+/u', ' ', $data['name']) ?? '');

        if (ProfileOrganizations::key($data['name']) === '') {
            throw ValidationException::withMessages(['name' => 'Le nom est obligatoire.']);
        }

        if ($current !== null && (int) ($data['parent_id'] ?? 0) === $current->id) {
            throw ValidationException::withMessages(['parent_id' => 'Un établissement ne peut pas dépendre de lui-même.']);
        }

        // Seule une faculté ou une école dépend d'une université.
        if (($data['type'] ?? null) !== 'faculty') {
            $data['parent_id'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data): array
    {
        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'parent_id' => $data['parent_id'] ?? null,
            'governorate_id' => $data['governorate_id'] ?? null,
            'address' => $data['address'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && str_starts_with($path, 'institutions/logos/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
