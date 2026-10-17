<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\PostReaction;
use App\Models\PostShare;
use App\Models\PostView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->with(['author:id,name', 'attachments:id,post_id,disk,path,mime_type'])
            ->withCount(['comments', 'reactions', 'shares', 'views'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 12), 50));

        $posts->getCollection()->transform(function (Post $post): Post {
            $post->setAttribute(
                'attachments_payload',
                $post->attachments
                    ->filter(fn (PostAttachment $attachment) => Storage::disk($attachment->disk)->exists($attachment->path))
                    ->map(fn (PostAttachment $attachment) => [
                        'url' => Storage::disk($attachment->disk)->url($attachment->path),
                        'mime_type' => $attachment->mime_type,
                        'kind' => str_starts_with($attachment->mime_type, 'video/') ? 'video' : 'image',
                    ])->values()
            );

            return $post;
        });

        return response()->json($posts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'visibility' => ['required', 'in:public,authenticated,entity,department,club,restricted'],
            'type' => ['required', 'in:text,image,link,announcement,project_update,activity_report,event_announcement'],
            'sentiment' => ['nullable', 'in:positive,neutral,negative,inspired,grateful'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['file', 'image', 'max:5120'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:30720'],
        ]);

        $post = Post::query()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
            'visibility' => $data['visibility'],
            'type' => $data['type'],
            'sentiment' => $data['sentiment'] ?? 'neutral',
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

        return response()->json([
            'message' => 'Publication creee.',
            'data' => $post,
        ], 201);
    }

    public function react(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'reaction_type' => ['required', 'in:like,support,insightful,celebrate'],
        ]);

        $reaction = PostReaction::query()->updateOrCreate(
            [
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
            ],
            [
                'reaction_type' => $data['reaction_type'],
            ]
        );

        return response()->json(['message' => 'Reaction enregistree.', 'data' => $reaction]);
    }

    public function comment(Request $request, Post $post): JsonResponse
    {
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
                return response()->json([
                    'message' => 'Commentaire parent invalide.',
                ], 422);
            }
        }

        $comment = Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'content' => $data['content'],
        ]);

        return response()->json(['message' => 'Commentaire ajoute.', 'data' => $comment], 201);
    }

    public function share(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', 'in:internal,copy_link,external'],
        ]);

        $share = PostShare::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'channel' => $data['channel'] ?? 'internal',
        ]);

        return response()->json(['message' => 'Partage comptabilise.', 'data' => $share], 201);
    }

    public function view(Request $request, Post $post): JsonResponse
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

        return response()->json(['message' => 'Vue comptabilisee.']);
    }
}
