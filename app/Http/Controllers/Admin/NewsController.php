<?php

namespace App\Http\Controllers\Admin;

use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\SiteNews;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        return redirect()->route('admin.news.edit', $news)->with('status', trim('Actualité créée. '.$this->relayToFeed($request, $news)));
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

        return back()->with('status', trim('Actualité enregistrée. '.$this->relayToFeed($request, $news)));
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

    /**
     * Publie l'article dans le fil T.Link si la case est cochée (une seule fois par article).
     * Retourne une phrase à ajouter au message de confirmation, ou une chaîne vide.
     */
    private function relayToFeed(Request $request, SiteNews $news): string
    {
        if (! $request->boolean('publish_to_feed') || $news->feed_post_id !== null) {
            return '';
        }

        if (! $news->isVisible()) {
            return 'L’article n’est pas encore en ligne : il n’a pas été publié dans le fil T.Link.';
        }

        $parts = array_filter([$news->text('title', 'fr'), $news->summary('fr'), $news->publicUrl('fr')]);

        $post = Post::query()->create([
            'user_id' => $request->user()->id,
            'scope' => $news->scope,
            'type' => 'text',
            'visibility' => 'public',
            'sentiment' => 'neutral',
            'content' => Str::limit(implode("\n\n", $parts), 2900, '…'),
            'published_at' => now(),
        ]);

        $this->copyCoverToPost($news, $post);
        $news->update(['feed_post_id' => $post->id]);

        $this->audit($request, 'admin.news.relayed', $news, ['post_id' => $post->id]);

        return 'Article publié dans le fil T.Link.';
    }

    /** La pièce jointe du fil est une copie : supprimer l'un ne touche pas l'autre. */
    private function copyCoverToPost(SiteNews $news, Post $post): void
    {
        $disk = Storage::disk('public');

        if (! $news->cover_path || ! $disk->exists($news->cover_path)) {
            return;
        }

        $target = 'posts/images/'.Str::random(24).'.'.(strtolower(pathinfo($news->cover_path, PATHINFO_EXTENSION)) ?: 'jpg');
        $disk->copy($news->cover_path, $target);

        PostAttachment::query()->create([
            'post_id' => $post->id,
            'disk' => 'public',
            'path' => $target,
            'mime_type' => (string) ($disk->mimeType($target) ?: 'image/jpeg'),
            'size_bytes' => (int) $disk->size($target),
        ]);

        $post->update(['type' => 'image']);
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
