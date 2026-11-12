<?php

namespace App\Http\Controllers;

use App\Models\Advert;
use App\Models\Comment;
use App\Models\Governorate;
use App\Models\Partner;
use App\Support\AccessScope;
use App\Support\FeedPostPresenter;
use Illuminate\Http\JsonResponse;
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
    private const PER_PAGE = 12;

    private const VISIBILITIES = ['public', 'authenticated', 'entity', 'department', 'club', 'restricted'];

    public function index(Request $request): View
    {
        $currentUserId = $request->user()?->id;
        $canPublishPublic = AccessScope::can($request->user(), 'post.publish_public');
        $allowedVisibilities = $canPublishPublic
            ? self::VISIBILITIES
            : array_values(array_filter(self::VISIBILITIES, fn (string $v): bool => $v !== 'public'));

        $posts = FeedPostPresenter::load()
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        $feedPayload = FeedPostPresenter::collection($posts->getCollection(), $currentUserId);

        $composerPhoto = $request->user()?->profile?->photo_path;
        $composerAvatar = $composerPhoto && Storage::disk('public')->exists($composerPhoto)
            ? Storage::disk('public')->url($composerPhoto)
            : null;

        return view('app', [
            'posts' => $posts,
            'feedPayload' => $feedPayload,
            'user' => $request->user(),
            'authUser' => $request->user() === null ? null : [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'avatar_url' => $composerAvatar,
            ],
            'composerCapabilities' => [
                'can_publish_public' => $canPublishPublic,
                'allowed_visibilities' => $allowedVisibilities,
            ],
            'advert' => Advert::forFeed(),
            'partners' => Partner::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (Partner $partner): array => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'logo_url' => $partner->logoUrl(),
                    'url' => $partner->safeUrl(),
                ])->values(),
            'links' => [
                'mission' => route('site.about', ['locale' => $this->siteLocale()]).'#mission',
                'badges' => route('badges.index'),
                'academy' => route('academy.index'),
                'challenges' => route('challenges.index'),
            ],
            'myBadges' => $request->user() === null ? [] : \App\Models\UserBadge::earnedBy($request->user()->id)
                ->map(fn (\App\Models\UserBadge $earned): array => [
                    'id' => $earned->badge->id,
                    'name' => $earned->badge->name,
                    'tier' => $earned->badge->tierLabel(),
                    'tier_key' => $earned->badge->tier,
                    'chip' => $earned->badge->chip(),
                    'icon' => $earned->badge->iconClass(),
                    'gradient' => $earned->badge->gradient(),
                ])->values(),
            'menu' => \App\Support\AppMenu::for($request),
            'storiesEnabled' => $request->user() !== null && \App\Support\Stories::enabled(),
            'stories' => $request->user() !== null && \App\Support\Stories::enabled() ? \App\Support\Stories::feedFor($request->user()) : [],
            'upcoming' => \App\Support\Challenges\ActivityCalendar::for($request->user()),
            'upcomingEnabled' => $request->user() !== null && \App\Support\Challenges\Challenges::enabled(),
            'pendingMemberships' => \App\Support\PendingMemberships::for($request->user()),
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    /** Langue du site vitrine pour les liens vers ses pages. */
    private function siteLocale(): string
    {
        $locale = app()->getLocale();

        return array_key_exists($locale, config('site.locales')) ? $locale : config('site.default_locale');
    }

    /** Publications les plus récentes, pour l'actualisation en direct du fil (compteurs, réactions, commentaires, nouveautés). */
    public function latest(Request $request): JsonResponse
    {
        $posts = FeedPostPresenter::load()->latest('published_at')->latest('id')->limit(self::PER_PAGE)->get();

        return response()->json(['posts' => FeedPostPresenter::collection($posts, $request->user()->id)]);
    }

    /** Réponse commune des actions : la publication à jour pour les appels JSON, sinon retour à la page avec un message. */
    private function done(Request $request, Post $post, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'post' => FeedPostPresenter::one($post, $request->user()->id)], $status);
        }

        return back()->with('status', $message);
    }

    public function storePost(Request $request): RedirectResponse|JsonResponse
    {
        $canPublishPublic = AccessScope::can($request->user(), 'post.publish_public');
        $allowedVisibilities = $canPublishPublic
            ? self::VISIBILITIES
            : array_values(array_filter(self::VISIBILITIES, fn (string $v): bool => $v !== 'public'));

        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'visibility' => ['required', Rule::in($allowedVisibilities)],
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

        return $this->done($request, $post, 'Publication partagee.', 201);
    }

    public function react(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'reaction_type' => ['required', 'in:like,support,insightful,celebrate'],
        ]);

        $existing = PostReaction::query()->where('post_id', $post->id)->where('user_id', $request->user()->id)->first();

        // Toucher deux fois la même réaction la retire ; une autre réaction remplace la précédente.
        if ($existing !== null && $existing->reaction_type === $data['reaction_type']) {
            $existing->delete();

            return $this->done($request, $post, 'Reaction retiree.');
        }

        PostReaction::query()->updateOrCreate(
            [
                'post_id' => $post->id,
                'user_id' => $request->user()->id,
            ],
            [
                'reaction_type' => $data['reaction_type'],
            ]
        );

        return $this->done($request, $post, 'Reaction enregistree.');
    }

    public function comment(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        if (! $post->comments_enabled) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Commentaires desactives pour ce post.'], 422)
                : back()->with('status', 'Commentaires desactives pour ce post.');
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
                return $request->expectsJson()
                    ? response()->json(['message' => 'Commentaire parent invalide.'], 422)
                    : back()->with('status', 'Commentaire parent invalide.');
            }
        }

        Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'content' => $data['content'],
        ]);

        return $this->done($request, $post, 'Commentaire publie.', 201);
    }

    public function updatePost(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
        ]);

        $post->content = $data['content'];
        $post->save();

        return $this->done($request, $post, 'Post modifie.');
    }

    public function destroyPost(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $post->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Post supprime.', 'deleted' => $post->id])
            : back()->with('status', 'Post supprime.');
    }

    public function toggleComments(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $post->comments_enabled = ! $post->comments_enabled;
        $post->save();

        return $this->done($request, $post, $post->comments_enabled ? 'Commentaires actives.' : 'Commentaires desactives.');
    }

    public function share(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', 'in:internal,copy_link,external'],
        ]);

        PostShare::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'channel' => $data['channel'] ?? 'internal',
        ]);

        return $this->done($request, $post, 'Partage comptabilise.');
    }

    public function view(Request $request, Post $post): RedirectResponse|JsonResponse
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

        return $this->done($request, $post, 'Vue comptabilisee.');
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
