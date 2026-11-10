<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\PostReaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Représentation JSON d'une publication du fil : la même structure sert au premier affichage, aux réponses des actions
 * (réaction, commentaire…) et à l'actualisation en direct, pour que l'écran se mette à jour sans recharger la page.
 */
final class FeedPostPresenter
{
    /** Relations et compteurs nécessaires à {@see self::collection()}. */
    public static function load(): \Illuminate\Database\Eloquent\Builder
    {
        return Post::query()
            ->with([
                'author:id,name',
                'author.profile:id,user_id,headline,photo_path,identity_verified_at',
                'attachments:id,post_id,disk,path,mime_type',
                'comments' => fn ($query) => $query
                    ->with(['author:id,name', 'author.profile:id,user_id,photo_path,identity_verified_at'])
                    ->reorder()->orderBy('created_at')->orderBy('id'),
            ])
            ->withCount(['comments', 'reactions', 'shares', 'views']);
    }

    /** Publication unique, rechargée depuis la base. */
    public static function one(Post $post, ?int $userId): array
    {
        return self::collection(self::load()->whereKey($post->id)->get(), $userId)->first() ?? [];
    }

    /**
     * @param  iterable<Post>  $posts
     * @return Collection<int, array<string, mixed>>
     */
    public static function collection(iterable $posts, ?int $userId): Collection
    {
        $posts = collect($posts);
        $ids = $posts->pluck('id')->all();

        $breakdown = PostReaction::query()
            ->selectRaw('post_id, reaction_type, COUNT(*) as aggregate_count')
            ->whereIn('post_id', $ids)
            ->groupBy('post_id', 'reaction_type')
            ->get()
            ->groupBy('post_id')
            ->map(fn (Collection $rows): array => $rows->pluck('aggregate_count', 'reaction_type')->map(fn ($count): int => (int) $count)->all());

        $mine = $userId === null ? collect() : PostReaction::query()
            ->whereIn('post_id', $ids)->where('user_id', $userId)->pluck('reaction_type', 'post_id');

        return $posts->map(fn (Post $post): array => self::present($post, $userId, $breakdown->get($post->id, []), $mine->get($post->id)))->values();
    }

    private static function avatar(?string $path): ?string
    {
        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    private static function present(Post $post, ?int $userId, array $reactions, ?string $myReaction): array
    {
        $topLevel = $post->comments->whereNull('parent_id');

        return [
            'id' => $post->id,
            'content' => $post->content,
            'type' => $post->type,
            'visibility' => $post->visibility,
            'sentiment' => $post->sentiment,
            'comments_enabled' => (bool) $post->comments_enabled,
            'can_manage' => $userId !== null && $userId === (int) $post->user_id,
            'published_at' => optional($post->published_at)?->toIso8601String(),
            'author' => $post->author
                ? [
                    'id' => $post->author->id,
                    'name' => $post->author->name,
                    'headline' => $post->author->profile?->headline,
                    'verified' => $post->author->profile?->identity_verified_at !== null,
                    'avatar_url' => self::avatar($post->author->profile?->photo_path),
                ]
                : null,
            'attachments' => $post->attachments
                ->filter(fn (PostAttachment $attachment) => Storage::disk($attachment->disk)->exists($attachment->path))
                ->map(fn (PostAttachment $attachment): array => [
                    'url' => route('posts.attachments.show', ['attachment' => $attachment->id]),
                    'mime_type' => $attachment->mime_type,
                    'kind' => str_starts_with($attachment->mime_type, 'video/') ? 'video' : 'image',
                ])->values(),
            'counts' => [
                'comments' => (int) $post->comments_count,
                'reactions' => (int) $post->reactions_count,
                'shares' => (int) $post->shares_count,
                'views' => (int) $post->views_count,
            ],
            'reactions' => $reactions,
            'my_reaction' => $myReaction,
            // Les cinq derniers commentaires : un nouveau commentaire apparaît toujours dans la liste.
            'comments_preview' => $topLevel->slice(-5)->map(fn (Comment $comment): array => self::presentComment($comment) + [
                'replies' => $post->comments->where('parent_id', $comment->id)->slice(-3)->map(fn (Comment $reply): array => self::presentComment($reply))->values(),
            ])->values(),
        ];
    }

    private static function presentComment(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'author' => $comment->author ? $comment->author->name : 'Membre',
            'author_id' => $comment->user_id,
            'author_verified' => $comment->author?->profile?->identity_verified_at !== null,
            'author_avatar_url' => self::avatar($comment->author?->profile?->photo_path),
            'created_at' => optional($comment->created_at)?->toIso8601String(),
        ];
    }
}
