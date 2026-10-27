<?php

namespace App\Http\Controllers\Admin;

use App\Models\SiteProject;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pages « projet » du site vitrine. Un projet est un périmètre de type « Projet » (Structure > Périmètres) :
 * ici on rédige sa page (présentation et chiffres clés). Réservé à l'administration globale.
 */
class ProjectController extends AdminController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.projects.index', ['projects' => SiteProject::allPages()]);
    }

    public function edit(Request $request, string $scope): View
    {
        $this->authorizeGlobal($request);
        $this->assertProjectScope($scope);

        return view('admin.projects.form', ['project' => SiteProject::forScope($scope), 'locales' => config('site.locales')]);
    }

    public function update(Request $request, string $scope): RedirectResponse
    {
        $this->authorizeGlobal($request);
        $this->assertProjectScope($scope);

        $project = SiteProject::forScope($scope);
        $values = $this->validated($request);
        $values['slug'] = $scope;
        $values['scope'] = $scope;
        $values['sort_order'] = (int) $request->input('sort_order', $project->sort_order);
        $oldCover = null;

        if ($request->hasFile('cover')) {
            $oldCover = $project->cover_path;
            $values['cover_path'] = $request->file('cover')->store('projects/covers', 'public');
        } elseif ($request->boolean('cover_remove')) {
            $oldCover = $project->cover_path;
            $values['cover_path'] = null;
        }

        $project->fill($values)->save();
        $this->deleteCover($oldCover);

        $this->audit($request, $project->wasRecentlyCreated ? 'admin.project.created' : 'admin.project.updated', $project, ['scope' => $scope, 'published' => $project->is_published]);

        return redirect()->route('admin.projects.edit', ['scope' => $scope])->with('status', 'Page du projet enregistrée.');
    }

    /** Efface le contenu de la page : le projet reste affiché avec les informations de son périmètre. */
    public function destroy(Request $request, string $scope): RedirectResponse
    {
        $this->authorizeGlobal($request);
        $this->assertProjectScope($scope);

        $project = SiteProject::query()->where('scope', $scope)->firstOrFail();

        $this->audit($request, 'admin.project.deleted', $project, ['scope' => $scope]);

        $cover = $project->cover_path;
        $project->delete();
        $this->deleteCover($cover);

        return redirect()->route('admin.projects.index')->with('status', 'Contenu de la page supprimé.');
    }

    private function assertProjectScope(string $scope): void
    {
        abort_unless(OrgScopes::type($scope) === 'project', 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['nullable', 'array'],
            'name.*' => ['nullable', 'string', 'max:160'],
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
            'stats.*.value.required_with' => 'Indiquez le chiffre pour chaque libellé.',
            'stats.*.label.fr.required_with' => 'Indiquez le libellé français de chaque chiffre.',
        ]);

        $figures = collect($data['stats'] ?? [])
            ->map(fn (array $row): array => [
                'value' => trim((string) ($row['value'] ?? '')),
                'label' => array_map(fn ($label): string => trim((string) $label), Arr::only((array) ($row['label'] ?? []), ['fr', 'en', 'ar'])),
            ])
            ->filter(fn (array $row): bool => $row['value'] !== '')
            ->values()
            ->all();

        return [
            'name' => $this->localized($data['name'] ?? []),
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
