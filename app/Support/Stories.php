<?php

namespace App\Support;

use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Stories 24 h du fil : publication, regroupement par auteur et nettoyage des stories expirées. */
final class Stories
{
    public const MODULE = 'feed.stories';

    /** Nombre maximum de stories actives par membre. */
    public const MAX_ACTIVE_PER_USER = 10;

    /** Nombre maximum d'auteurs affichés dans la barre. */
    private const MAX_AUTHORS = 30;

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public static function enabled(): bool
    {
        return ModuleCatalog::isActive(self::MODULE);
    }

    public static function acceptedMimes(): array
    {
        return [...self::IMAGE_MIMES, ...self::VIDEO_MIMES];
    }

    public static function create(User $user, UploadedFile $file, ?string $caption): Story
    {
        $mime = (string) $file->getMimeType();
        $isVideo = in_array($mime, self::VIDEO_MIMES, true);
        $extension = $isVideo
            ? match ($mime) {
                'video/webm' => 'webm',
                'video/quicktime' => 'mov',
                default => 'mp4',
            }
            : match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };

        $path = $file->storeAs('stories/'.$user->id, Str::uuid().'.'.$extension, Story::DISK);

        return Story::query()->create([
            'user_id' => $user->id,
            'kind' => $isVideo ? 'video' : 'image',
            'path' => $path,
            'mime_type' => $mime,
            'caption' => filled($caption) ? trim($caption) : null,
            'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
        ]);
    }

    /**
     * Stories actives regroupées par auteur : les siennes d'abord, puis les non vues, puis les déjà vues.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function feedFor(User $viewer): array
    {
        $stories = Story::query()->active()->with('author.profile')->withCount('views')->orderBy('created_at')->limit(400)->get();

        if ($stories->isEmpty()) {
            return [];
        }

        $seen = StoryView::query()->where('user_id', $viewer->id)->whereIn('story_id', $stories->pluck('id'))->pluck('story_id')->flip();

        $groups = $stories->groupBy('user_id')->map(function ($items) use ($viewer, $seen): array {
            $author = $items->first()->author;
            $mine = $author->id === $viewer->id;
            $photo = $author->profile?->photo_path;

            return [
                'user' => [
                    'id' => $author->id,
                    'name' => $author->name,
                    'avatar_url' => $photo && Storage::disk('public')->exists($photo) ? Storage::disk('public')->url($photo) : null,
                    'is_me' => $mine,
                ],
                'all_seen' => $mine || $items->every(fn (Story $story): bool => $seen->has($story->id)),
                'latest' => $items->max('created_at')?->getTimestamp() ?? 0,
                'items' => $items->take(-self::MAX_ACTIVE_PER_USER)->map(fn (Story $story): array => [
                    'id' => $story->id,
                    'type' => $story->kind,
                    'url' => $story->mediaUrl(),
                    'caption' => $story->caption,
                    'created_at' => $story->created_at?->toIso8601String(),
                    'seen' => $mine || $seen->has($story->id),
                    'views' => $mine ? (int) $story->views_count : null,
                ])->values()->all(),
            ];
        });

        // Priorité décroissante : mes stories, puis celles que je n'ai pas vues, puis les plus récentes.
        return $groups->sort(function (array $a, array $b): int {
            return [$b['user']['is_me'], ! $b['all_seen'], $b['latest']] <=> [$a['user']['is_me'], ! $a['all_seen'], $a['latest']];
        })->take(self::MAX_AUTHORS)->map(fn (array $group): array => collect($group)->except('latest')->all())->values()->all();
    }

    /** Supprime les stories expirées (lignes et fichiers). */
    public static function purgeExpired(): int
    {
        $count = 0;

        Story::query()->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($stories) use (&$count): void {
            foreach ($stories as $story) {
                Storage::disk(Story::DISK)->delete($story->path);
                $story->delete();
                $count++;
            }
        });

        return $count;
    }
}
