<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Governorate;
use App\Support\OrgScopes;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\PostReaction;
use App\Models\PostShare;
use App\Models\PostView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request): View
    {
        $currentUserId = $request->user()?->id;

        $posts = Post::query()
            ->with([
                'author:id,name',
                'author.profile:id,user_id,headline,photo_path',
                'attachments:id,post_id,disk,path,mime_type',
                'comments' => fn ($query) => $query
                    ->with(['author:id,name', 'author.profile:id,user_id,photo_path'])
                    ->orderBy('created_at'),
            ])
            ->withCount(['comments', 'reactions', 'shares', 'views'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(12);

        $feedPayload = $posts->getCollection()->map(function (Post $post) use ($currentUserId): array {
            $reactionBreakdown = PostReaction::query()
                ->selectRaw('reaction_type, COUNT(*) as aggregate_count')
                ->where('post_id', $post->id)
                ->groupBy('reaction_type')
                ->pluck('aggregate_count', 'reaction_type')
                ->toArray();

            return [
                'id' => $post->id,
                'content' => $post->content,
                'type' => $post->type,
                'visibility' => $post->visibility,
                'sentiment' => $post->sentiment,
                'comments_enabled' => (bool) $post->comments_enabled,
                'can_manage' => $currentUserId ? ((int) $currentUserId === (int) $post->user_id) : false,
                'published_at' => optional($post->published_at)?->toIso8601String(),
                'author' => $post->author
                    ? [
                        'id' => $post->author->id,
                        'name' => $post->author->name,
                        'headline' => $post->author->profile?->headline,
                        'avatar_url' => ($post->author->profile?->photo_path
                            && Storage::disk('public')->exists($post->author->profile->photo_path))
                            ? Storage::disk('public')->url($post->author->profile->photo_path)
                            : null,
                    ]
                    : null,
                'attachments' => $post->attachments
                    ->filter(fn (PostAttachment $attachment) => Storage::disk($attachment->disk)->exists($attachment->path))
                    ->map(function (PostAttachment $attachment): array {
                        return [
                            'url' => route('posts.attachments.show', ['attachment' => $attachment->id]),
                            'mime_type' => $attachment->mime_type,
                            'kind' => str_starts_with($attachment->mime_type, 'video/') ? 'video' : 'image',
                        ];
                    })->values(),
                'counts' => [
                    'comments' => (int) $post->comments_count,
                    'reactions' => (int) $post->reactions_count,
                    'shares' => (int) $post->shares_count,
                    'views' => (int) $post->views_count,
                ],
                'reactions' => $reactionBreakdown,
                'comments_preview' => $post->comments
                    ->whereNull('parent_id')
                    ->take(5)
                    ->map(function (Comment $comment) use ($post): array {
                        $authorPhotoPath = $comment->author?->profile?->photo_path;

                        return [
                            'id' => $comment->id,
                            'content' => $comment->content,
                            'author' => $comment->author ? $comment->author->name : 'Membre',
                            'author_avatar_url' => ($authorPhotoPath && Storage::disk('public')->exists($authorPhotoPath))
                                ? Storage::disk('public')->url($authorPhotoPath)
                                : null,
                            'created_at' => optional($comment->created_at)?->toIso8601String(),
                            'replies' => $post->comments
                                ->where('parent_id', $comment->id)
                                ->take(3)
                                ->map(function (Comment $reply): array {
                                    $replyPhotoPath = $reply->author?->profile?->photo_path;

                                    return [
                                        'id' => $reply->id,
                                        'content' => $reply->content,
                                        'author' => $reply->author ? $reply->author->name : 'Membre',
                                        'author_avatar_url' => ($replyPhotoPath && Storage::disk('public')->exists($replyPhotoPath))
                                            ? Storage::disk('public')->url($replyPhotoPath)
                                            : null,
                                        'created_at' => optional($reply->created_at)?->toIso8601String(),
                                    ];
                                })->values(),
                        ];
                    })->values(),
            ];
        })->values();

        return view('app', [
            'posts' => $posts,
            'feedPayload' => $feedPayload,
            'user' => $request->user(),
            'entities' => collect(OrgScopes::ALL)->map(fn (string $name, string $slug): array => ['id' => $slug, 'name' => $name, 'slug' => $slug])->values(),
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function storePost(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'visibility' => ['required', 'in:public,authenticated,entity,department,club,restricted'],
            'sentiment' => ['nullable', 'in:positive,neutral,negative,inspired,grateful'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['file', 'image', 'max:5120'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:30720'],
        ]);

        $postType = 'text';
        if ($request->hasFile('video')) {
            $postType = 'image';
        }
        if ($request->hasFile('photos')) {
            $postType = 'image';
        }

        $post = Post::query()->create([
            'user_id' => $request->user()->id,
            'type' => $postType,
            'visibility' => $data['visibility'],
            'sentiment' => $data['sentiment'] ?? 'neutral',
            'content' => $data['content'],
            'published_at' => now(),
        ]);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos', []) as $photo) {
                $path = $photo->store('posts/images', 'public');
                PostAttachment::query()->create([
                    'post_id' => $post->id,
                    'disk' => 'public',
                    'path' => $path,
                    'mime_type' => (string) $photo->getMimeType(),
                    'size_bytes' => (int) $photo->getSize(),
                ]);
            }
        }

        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $path = $video->store('posts/videos', 'public');
            PostAttachment::query()->create([
                'post_id' => $post->id,
                'disk' => 'public',
                'path' => $path,
                'mime_type' => (string) $video->getMimeType(),
                'size_bytes' => (int) $video->getSize(),
            ]);
        }

        return back()->with('status', 'Publication partagee.');
    }

    public function react(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'reaction_type' => ['required', 'in:like,support,insightful,celebrate'],
        ]);

        PostReaction::query()->updateOrCreate(
            [
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
            ],
            [
                'reaction_type' => $data['reaction_type'],
            ]
        );

        return back()->with('status', 'Reaction enregistree.');
    }

    public function comment(Request $request, Post $post): RedirectResponse
    {
        if (! $post->comments_enabled) {
            return back()->with('status', 'Commentaires desactives pour ce post.');
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('comments', 'id')],
        ]);

        if (!empty($data['parent_id'])) {
            $isParentInSamePost = Comment::query()
                ->whereKey($data['parent_id'])
                ->where('post_id', $post->id)
                ->exists();

            if (! $isParentInSamePost) {
                return back()->with('status', 'Commentaire parent invalide.');
            }
        }

        Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'content' => $data['content'],
        ]);

        return back()->with('status', 'Commentaire publie.');
    }

    public function updatePost(Request $request, Post $post): RedirectResponse
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
        ]);

        $post->content = $data['content'];
        $post->save();

        return back()->with('status', 'Post modifie.');
    }

    public function destroyPost(Request $request, Post $post): RedirectResponse
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $post->delete();

        return back()->with('status', 'Post supprime.');
    }

    public function toggleComments(Request $request, Post $post): RedirectResponse
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $post->comments_enabled = ! $post->comments_enabled;
        $post->save();

        return back()->with('status', $post->comments_enabled ? 'Commentaires actives.' : 'Commentaires desactives.');
    }

    public function share(Request $request, Post $post): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', 'in:internal,copy_link,external'],
        ]);

        PostShare::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'channel' => $data['channel'] ?? 'internal',
        ]);

        return back()->with('status', 'Partage comptabilise.');
    }

    public function view(Request $request, Post $post): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            PostView::query()->firstOrCreate([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ], [
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'viewed_at' => now(),
            ]);
        } else {
            PostView::query()->firstOrCreate([
                'post_id' => $post->id,
                'user_id' => null,
                'ip_address' => $request->ip(),
            ], [
                'user_agent' => (string) $request->userAgent(),
                'viewed_at' => now(),
            ]);
        }

        return back();
    }

    public function showAttachment(PostAttachment $attachment): StreamedResponse
    {
        $disk = $attachment->disk ?: 'public';

        if (! Storage::disk($disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($disk)->response(
            $attachment->path,
            basename($attachment->path),
            [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'public, max-age=86400',
            ]
        );
    }
}
