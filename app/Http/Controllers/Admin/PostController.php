<?php

namespace App\Http\Controllers\Admin;

use App\Models\Post;
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
        'entity' => 'Entité',
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
            'entity' => ['nullable', 'integer'],
        ]);

        $posts = $this->scoped(Post::query(), $request, 'post.moderate')
            ->with(['author:id,name', 'entity:id,name'])
            ->withCount(['comments', 'reactions'])
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $query->where(fn ($inner) => $inner->where('content', 'like', "%{$needle}%")
                    ->orWhereHas('author', fn ($author) => $author->where('name', 'like', "%{$needle}%")));
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['visibility'] ?? null, fn ($query, $visibility) => $query->where('visibility', $visibility))
            ->when($filters['entity'] ?? null, fn ($query, $entity) => $query->where('entity_id', $entity))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'filters' => $filters,
            'types' => self::TYPES,
            'visibilities' => self::VISIBILITIES,
            'entities' => $this->entityOptions($request, 'post.moderate'),
        ]);
    }

    public function toggleComments(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeEntity($request, 'post.moderate', $post->entity_id);

        $post->update(['comments_enabled' => ! $post->comments_enabled]);

        $this->audit($request, 'admin.post.comments_toggled', $post, ['comments_enabled' => $post->comments_enabled]);

        return back()->with('status', $post->comments_enabled ? 'Commentaires réactivés.' : 'Commentaires désactivés.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorizeEntity($request, 'post.moderate', $post->entity_id);

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
