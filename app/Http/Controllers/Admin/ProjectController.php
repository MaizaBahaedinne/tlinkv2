<?php

namespace App\Http\Controllers\Admin;

use App\Models\SiteProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pages « projet » du site vitrine : présentation traduite et chiffres clés (réservé à l'administration globale).
 */
class ProjectController extends AdminController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.projects.index', ['projects' => SiteProject::query()->ordered()->get()]);
    }

    public function create(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.projects.form', ['project' => new SiteProject(['status' => 'ongoing', 'is_published' => false]), 'locales' => config('site.locales')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $values = $this->validated($request, null);
        $values['sort_order'] = ((int) SiteProject::query()->max('sort_order')) + 1;

        if ($request->hasFile('cover')) {
            $values['cover_path'] = $request->file('cover')->store('projects/covers', 'public');
        }

        $project = SiteProject::query()->create($values);

        $this->audit($request, 'admin.project.created', $project, ['name' => $project->text('name', 'fr')]);

        return redirect()->route('admin.projects.edit', $project)->with('status', 'Projet créé.');
    }

    public function edit(Request $request, SiteProject $project): View
    {
        $this->authorizeGlobal($request);

        return view('admin.projects.form', ['project' => $project, 'locales' => config('site.locales')]);
    }

    public function update(Request $request, SiteProject $project): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $values = $this->validated($request, $project);
        $values['sort_order'] = (int) $request->input('sort_order', $project->sort_order);
        $oldCover = null;

        if ($request->hasFile('cover')) {
            $oldCover = $project->cover_path;
            $values['cover_path'] = $request->file('cover')->store('projects/covers', 'public');
        } elseif ($request->boolean('cover_remove')) {
            $oldCover = $project->cover_path;
            $values['cover_path'] = null;
        }

        $project->update($values);
        $this->deleteCover($oldCover);

        $this->audit($request, 'admin.project.updated', $project, ['name' => $project->text('name', 'fr'), 'published' => $project->is_published]);

        return back()->with('status', 'Projet enregistré.');
    }

    public function destroy(Request $request, SiteProject $project): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $this->audit($request, 'admin.project.deleted', $project, ['name' => $project->text('name', 'fr')]);

        $cover = $project->cover_path;
        $project->delete();
        $this->deleteCover($cover);

        return redirect()->route('admin.projects.index')->with('status', 'Projet supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SiteProject $project): array
    {
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('site_projects', 'slug')->ignore($project?->id)],
            'name' => ['required', 'array'],
            'name.fr' => ['required', 'string', 'max:160'],
            'name.en' => ['nullable', 'string', 'max:160'],
            'name.ar' => ['nullable', 'string', 'max:160'],
            'summary' => ['nullable', 'array'],
            'summary.*' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'array'],
            'body.*' => ['nullable', 'string', 'max:8000'],
            'status' => ['required', Rule::in(array_keys(SiteProject::STATUSES))],
            'started_year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'stats' => ['nullable', 'array', 'max:'.SiteProject::MAX_FIGURES],
            'stats.*.value' => ['nullable', 'string', 'max:30', 'required_with:stats.*.label.fr'],
            'stats.*.label.fr' => ['nullable', 'string', 'max:80', 'required_with:stats.*.value'],
            'stats.*.label.en' => ['nullable', 'string', 'max:80'],
            'stats.*.label.ar' => ['nullable', 'string', 'max:80'],
        ], [
            'slug.regex' => 'L’adresse ne peut contenir que des minuscules, des chiffres et des tirets.',
            'slug.unique' => 'Cette adresse est déjà utilisée par un autre projet.',
            'name.fr.required' => 'Le nom en français est obligatoire.',
            'stats.*.value.required_with' => 'Indiquez le chiffre pour chaque libellé.',
            'stats.*.label.fr.required_with' => 'Indiquez le libellé français de chaque chiffre.',
        ]);

        $slug = trim((string) ($data['slug'] ?? ''));

        if ($slug === '') {
            $slug = $this->uniqueSlug(Str::slug($data['name']['fr']), $project);
        }

        $figures = collect($data['stats'] ?? [])
            ->map(fn (array $row): array => [
                'value' => trim((string) ($row['value'] ?? '')),
                'label' => array_map(fn ($label): string => trim((string) $label), Arr::only((array) ($row['label'] ?? []), ['fr', 'en', 'ar'])),
            ])
            ->filter(fn (array $row): bool => $row['value'] !== '')
            ->values()
            ->all();

        return [
            'slug' => $slug,
            'name' => $this->localized($data['name']),
            'summary' => $this->localized($data['summary'] ?? []),
            'body' => $this->localized($data['body'] ?? []),
            'stats' => $figures,
            'status' => $data['status'],
            'started_year' => $data['started_year'] ?? null,
            'website_url' => $data['website_url'] ?? null,
            'is_published' => $request->boolean('is_published'),
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function localized(array $values): array
    {
        return array_map(fn ($value): string => trim((string) $value), Arr::only($values, ['fr', 'en', 'ar']));
    }

    private function uniqueSlug(string $base, ?SiteProject $project): string
    {
        $base = $base !== '' ? Str::limit($base, 70, '') : 'projet';
        $slug = $base;
        $i = 2;

        while (SiteProject::query()->where('slug', $slug)->when($project, fn ($query) => $query->whereKeyNot($project->id))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function deleteCover(?string $path): void
    {
        if ($path && str_starts_with($path, 'projects/covers/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
