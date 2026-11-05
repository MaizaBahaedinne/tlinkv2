<?php

namespace App\Http\Controllers\Admin;

use App\Models\AppRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Journal des versions : notes de version rédigées et publiées par l'administration globale.
 */
class ReleaseController extends AdminController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        $status = $request->query('status');
        $status = array_key_exists((string) $status, AppRelease::STATUSES) ? (string) $status : null;

        return view('admin.releases.index', [
            'releases' => AppRelease::query()
                ->with('author:id,name')
                ->when($status, fn ($query, $value) => $query->where('status', $value))
                ->orderByRaw('published_at is null desc')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.releases.form', ['release' => new AppRelease(['status' => 'draft'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $data = $this->validated($request);

        $release = AppRelease::query()->create($data + ['author_id' => $request->user()->id]);

        $this->audit($request, 'admin.release.created', $release, ['version' => $release->version, 'status' => $release->status]);

        return redirect()->route('admin.releases.index')->with('status', 'Version '.$release->version.' enregistrée.');
    }

    public function edit(Request $request, AppRelease $release): View
    {
        $this->authorizeGlobal($request);

        return view('admin.releases.form', ['release' => $release]);
    }

    public function update(Request $request, AppRelease $release): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $data = $this->validated($request, $release);

        $release->update($data);

        $this->audit($request, 'admin.release.updated', $release, ['version' => $release->version, 'status' => $release->status]);

        return redirect()->route('admin.releases.index')->with('status', 'Version '.$release->version.' mise à jour.');
    }

    public function toggle(Request $request, AppRelease $release): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $publish = $release->status !== 'published';

        $release->update([
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $publish ? ($release->published_at ?? now()) : $release->published_at,
        ]);

        $this->audit($request, $publish ? 'admin.release.published' : 'admin.release.unpublished', $release, ['version' => $release->version]);

        return back()->with('status', $publish ? 'Version '.$release->version.' publiée.' : 'Version '.$release->version.' repassée en brouillon.');
    }

    public function destroy(Request $request, AppRelease $release): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $this->audit($request, 'admin.release.deleted', $release, ['version' => $release->version]);
        $release->delete();

        return redirect()->route('admin.releases.index')->with('status', 'Version supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AppRelease $release = null): array
    {
        $rules = [
            'version' => ['required', 'string', 'max:40', 'regex:/^v?\d+(\.\d+){0,3}([-+][0-9A-Za-z.-]+)?$/', Rule::unique('app_releases', 'version')->ignore($release?->id)],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:1500'],
            'status' => ['required', Rule::in(array_keys(AppRelease::STATUSES))],
            'published_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ];

        foreach (array_keys(AppRelease::TYPES) as $type) {
            $rules['changes_'.$type] = ['nullable', 'string', 'max:8000'];
        }

        $data = $request->validate($rules, [
            'version.regex' => 'Indiquez un numéro de version, par exemple 1.4.0 ou v2.0.1.',
            'version.unique' => 'Cette version existe déjà.',
            'published_at.date_format' => 'Date de publication invalide.',
        ]);

        $changes = [];

        foreach (array_keys(AppRelease::TYPES) as $type) {
            $lines = collect(preg_split('/\R/u', (string) ($data['changes_'.$type] ?? '')) ?: [])
                ->map(fn (string $line): string => trim(ltrim(trim($line), '-*• ')))
                ->filter()
                ->values()
                ->all();

            if ($lines !== []) {
                $changes[$type] = $lines;
            }
        }

        $publishedAt = $data['published_at'] ?? null;

        if ($data['status'] === 'published' && ! $publishedAt) {
            $publishedAt = $release?->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i');
        }

        return [
            'version' => trim($data['version']),
            'title' => trim($data['title']),
            'summary' => isset($data['summary']) && trim($data['summary']) !== '' ? trim($data['summary']) : null,
            'notes' => $changes,
            'status' => $data['status'],
            'published_at' => $publishedAt ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d\TH:i', $publishedAt, config('app.timezone')) : null,
        ];
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
