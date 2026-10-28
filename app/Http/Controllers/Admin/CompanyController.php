<?php

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Support\ProfileOrganizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Entreprises citées dans les profils des membres : la base se remplit à mesure que les membres saisissent
 * leurs expériences, l'administration y ajoute le logo affiché sur les profils.
 */
class CompanyController extends AdminController
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $onlyMissing = $request->boolean('sans_logo');

        $companies = Company::query()
            ->with('creator:id,name')
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '\\%_').'%'))
            ->when($onlyMissing, fn ($query) => $query->whereNull('logo_path'))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.companies.index', [
            'companies' => $companies,
            'term' => $term,
            'onlyMissing' => $onlyMissing,
            'total' => Company::query()->count(),
            'withoutLogo' => Company::query()->whereNull('logo_path')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = ProfileOrganizations::key($data['name']);

        if (Company::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Cette entreprise existe déjà.']);
        }

        $company = Company::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'website_url' => $data['website_url'] ?? null,
            'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('companies/logos', 'public') : null,
            'created_by' => $request->user()->id,
        ]);

        $this->audit($request, 'admin.company.created', $company, ['name' => $company->name]);

        return back()->with('status', 'Entreprise ajoutée.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = ProfileOrganizations::key($data['name']);

        if (Company::query()->where('slug', $slug)->whereKeyNot($company->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Une autre entreprise porte déjà ce nom.']);
        }

        $values = ['name' => $data['name'], 'slug' => $slug, 'website_url' => $data['website_url'] ?? null];
        $oldLogo = null;

        if ($request->hasFile('logo')) {
            $oldLogo = $company->logo_path;
            $values['logo_path'] = $request->file('logo')->store('companies/logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $oldLogo = $company->logo_path;
            $values['logo_path'] = null;
        }

        $company->update($values);
        $this->deleteLogo($oldLogo);

        $this->audit($request, 'admin.company.updated', $company, ['name' => $company->name]);

        return back()->with('status', 'Entreprise modifiée.');
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        $this->audit($request, 'admin.company.deleted', $company, ['name' => $company->name]);

        $logo = $company->logo_path;
        $company->delete();
        $this->deleteLogo($logo);

        return back()->with('status', 'Entreprise supprimée : les profils gardent le nom saisi, sans logo.');
    }

    /**
     * @return array{name: string, website_url?: ?string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $data['name'] = trim(preg_replace('/\s+/u', ' ', $data['name']) ?? '');

        if (ProfileOrganizations::key($data['name']) === '') {
            throw ValidationException::withMessages(['name' => 'Le nom est obligatoire.']);
        }

        return $data;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && str_starts_with($path, 'companies/logos/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
