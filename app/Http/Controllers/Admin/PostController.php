<?php

namespace App\Http\Controllers\Admin;

use App\Models\Post;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PostController extends AdminController
{
    public const TYPES = [
        'text' => 'Texte',
        'image' => 'Image',
        'link' => 'Lien',
        'announcement' => 'Annonce',
        'project_update' => 'Avancement de projet',
        'activity_report' => 'Compte rendu d’activité',
        'event_announcement' => 'Annonce d’événement',
    ];

    public const VISIBILITIES = [
        'public' => 'Public',
        'authenticated' => 'Membres connectés',
        'entity' => 'Périmètre',
        'department' => 'Département',
        'club' => 'Club',
        'restricted' => 'Restreint',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'visibility' => ['nullable', Rule::in(array_keys(self::VISIBILITIES))],
            'scope' => ['nullable', Rule::in(OrgScopes::slugs())],
        ]);

        $posts = $this->scoped(Post::query(), $request, 'post.moderate')
            ->with(['author:id,name'])
            ->withCount(['comments', 'reactions'])
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $query->where(fn ($inner) => $inner->where('content', 'like', "%{$needle}%")
                    ->orWhereHas('author', fn ($author) => $author->where('name', 'like', "%{$needle}%")));
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['visibility'] ?? null, fn ($query, $visibility) => $query->where('visibility', $visibility))
            ->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'filters' => $filters,
            'types' => self::TYPES,
            'visibilities' => self::VISIBILITIES,
            'scopes' => $this->scopeOptions($request, 'post.moderate'),
        ]);
    }

    public function toggleComments(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeScope($request, 'post.moderate', $post->scope);

        $post->update(['comments_enabled' => ! $post->comments_enabled]);

        $this->audit($request, 'admin.post.comments_toggled', $post, ['comments_enabled' => $post->comments_enabled]);

        return back()->with('status', $post->comments_enabled ? 'Commentaires réactivés.' : 'Commentaires désactivés.');
    }

    public function toggleSiteNews(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeScope($request, 'post.moderate', $post->scope);

        $enabled = ! $post->is_site_news;
        $post->is_site_news = $enabled;

        if ($enabled) {
            // Une actualité vitrine doit être publique et datée.
            $post->visibility = 'public';
            if ($post->published_at === null) {
                $post->published_at = now();
            }
        }

        $post->save();

        $this->audit($request, 'admin.post.site_news_toggled', $post, [
            'is_site_news' => $post->is_site_news,
            'visibility' => $post->visibility,
            'published_at' => optional($post->published_at)?->toIso8601String(),
        ]);

        return back()->with('status', $post->is_site_news
            ? 'Publication autorisée pour les actualités vitrine.'
            : 'Publication retirée des actualités vitrine.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeScope($request, 'post.moderate', $post->scope);

        $this->audit($request, 'admin.post.deleted', $post, [
            'author_id' => $post->user_id,
            'excerpt' => mb_substr((string) $post->content, 0, 120),
        ]);

        foreach ($post->attachments as $attachment) {
            try {
                Storage::disk($attachment->disk)->delete($attachment->path);
            } catch (Throwable) {
                // Le fichier physique est ignore s'il est deja absent ou si le disque est indisponible.
            }
        }

        $post->delete();

        return back()->with('status', 'Publication supprimée.');
    }
}
