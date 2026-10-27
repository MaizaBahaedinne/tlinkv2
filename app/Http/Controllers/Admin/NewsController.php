<?php

namespace App\Http\Controllers\Admin;

use App\Models\SiteNews;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Actualités du site vitrine. Système indépendant des publications du fil T.Link
 * (réservé à l'administration globale).
 */
class NewsController extends AdminController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $news = SiteNews::query()
            ->with('author:id,name')
            ->when(in_array($status, array_keys(SiteNews::STATUSES), true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.news.index', [
            'news' => $news,
            'status' => $status,
            'search' => $search,
            'scopes' => OrgScopes::all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.news.form', ['item' => new SiteNews(['status' => 'draft']), 'scopes' => OrgScopes::all(), 'locales' => config('site.locales')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $values = $this->validated($request);
        $values['author_id'] = $request->user()->id;

        if ($request->hasFile('cover')) {
            $values['cover_path'] = $request->file('cover')->store('news/covers', 'public');
        }

        $news = SiteNews::query()->create($values);

        $this->audit($request, 'admin.news.created', $news, ['title' => $news->text('title', 'fr'), 'status' => $news->status]);

        return redirect()->route('admin.news.edit', $news)->with('status', 'Actualité créée.');
    }

    public function edit(Request $request, SiteNews $news): View
    {
        $this->authorizeGlobal($request);

        return view('admin.news.form', ['item' => $news, 'scopes' => OrgScopes::all(), 'locales' => config('site.locales')]);
    }

    public function update(Request $request, SiteNews $news): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $values = $this->validated($request);
        $oldCover = null;

        if ($request->hasFile('cover')) {
            $oldCover = $news->cover_path;
            $values['cover_path'] = $request->file('cover')->store('news/covers', 'public');
        } elseif ($request->boolean('remove_cover')) {
            $oldCover = $news->cover_path;
            $values['cover_path'] = null;
        }

        $news->update($values);
        $this->deleteCover($oldCover);

        $this->audit($request, 'admin.news.updated', $news, ['title' => $news->text('title', 'fr'), 'status' => $news->status]);

        return back()->with('status', 'Actualité enregistrée.');
    }

    public function destroy(Request $request, SiteNews $news): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $this->audit($request, 'admin.news.deleted', $news, ['title' => $news->text('title', 'fr')]);

        $cover = $news->cover_path;
        $news->delete();
        $this->deleteCover($cover);

        return redirect()->route('admin.news.index')->with('status', 'Actualité supprimée.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title.fr' => ['required', 'string', 'max:200'],
            'title.en' => ['nullable', 'string', 'max:200'],
            'title.ar' => ['nullable', 'string', 'max:200'],
            'excerpt.fr' => ['nullable', 'string', 'max:300'],
            'excerpt.en' => ['nullable', 'string', 'max:300'],
            'excerpt.ar' => ['nullable', 'string', 'max:300'],
            'body.fr' => ['nullable', 'string', 'max:20000'],
            'body.en' => ['nullable', 'string', 'max:20000'],
            'body.ar' => ['nullable', 'string', 'max:20000'],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'scope' => ['nullable', 'string', Rule::in(OrgScopes::slugs())],
            'status' => ['required', Rule::in(array_keys(SiteNews::STATUSES))],
            'published_at' => ['nullable', 'date'],
        ], [
            'title.fr.required' => 'Le titre en français est obligatoire.',
        ]);

        $published = $data['status'] === 'published';
        $publishedAt = ! empty($data['published_at']) ? \Illuminate\Support\Carbon::parse($data['published_at']) : null;

        return [
            'title' => $this->translations($data['title'] ?? []),
            'excerpt' => $this->translations($data['excerpt'] ?? []) ?: null,
            'body' => $this->translations($data['body'] ?? []) ?: null,
            'scope' => ($data['scope'] ?? null) ?: null,
            'status' => $data['status'],
            // Publiée sans date : parution immédiate. Une date future programme la parution.
            'published_at' => $publishedAt ?? ($published ? now() : null),
        ];
    }

    /** @param  array<string, mixed>  $values  @return array<string, string> */
    private function translations(array $values): array
    {
        $clean = [];

        foreach (['fr', 'en', 'ar'] as $locale) {
            $value = trim((string) ($values[$locale] ?? ''));

            if ($value !== '') {
                $clean[$locale] = $value;
            }
        }

        return $clean;
    }

    private function deleteCover(?string $path): void
    {
        if ($path && str_starts_with($path, 'news/covers/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
