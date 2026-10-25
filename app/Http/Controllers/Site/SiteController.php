<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Support\OrgScopes;
use App\Support\PublicOrgChart;
use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Site public de la Fondation : ne lit que des donnees deja publiques dans T.Link
 * (publications `public`, evenements publies, postes de l'organigramme, clubs actifs).
 */
class SiteController extends Controller
{
    public function root(Request $request): RedirectResponse
    {
        $locale = $request->getPreferredLanguage(array_keys(config('site.locales'))) ?? config('site.default_locale');

        return redirect()->route('site.home', ['locale' => $locale]);
    }

    public function home(): View
    {
        $news = $this->publicPosts()->limit(3)->get();
        $events = $this->upcomingEvents()->limit(3)->get();

        return $this->render('site.home', [
            'stats' => $this->stats(),
            'news' => $news,
            'events' => $events,
            'scopes' => OrgScopes::all(),
            'scopeHierarchy' => OrgScopes::hierarchy(),
            'scopeDescriptions' => OrgScopes::descriptions(),
        ]);
    }

    public function about(): View
    {
        return $this->render('site.about', [
            'stats' => $this->stats(),
            'scopes' => OrgScopes::all(),
            'groups' => PublicOrgChart::teamByScope(),
        ]);
    }

    public function news(Request $request): View
    {
        $scope = $this->scopeFilter($request);

        return $this->render('site.news.index', [
            'posts' => $this->inScope($this->publicPosts(), $scope)->paginate(9)->withQueryString(),
            'scope' => $scope,
        ]);
    }

    public function newsShow(int $post): View
    {
        $post = $this->publicPosts()->with('attachments')->findOrFail($post);

        return $this->render('site.news.show', [
            'post' => $post,
            'media' => $this->visibleAttachments($post),
            'related' => $this->publicPosts()->whereKeyNot($post->id)->limit(3)->get(),
        ]);
    }

    public function events(Request $request): View
    {
        $scope = $this->scopeFilter($request);

        return $this->render('site.events.index', [
            'upcoming' => $this->inScope($this->upcomingEvents(), $scope)->get(),
            'past' => $this->inScope($this->publicEvents(), $scope)
                ->where('starts_at', '<', now()->startOfDay())
                ->orderByDesc('starts_at')
                ->paginate(9)
                ->withQueryString(),
            'scope' => $scope,
        ]);
    }

    public function eventShow(int $event): View
    {
        $event = $this->publicEvents()->findOrFail($event);

        return $this->render('site.events.show', ['event' => $event]);
    }

    public function clubs(Request $request): View
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(OrgScopes::slugs())],
            'gov' => ['nullable', 'integer', 'exists:governorates,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $filters;
        $applyFilters = fn (Builder $query, array $only): Builder => $query
            ->when(in_array('scope', $only, true) ? ($search['scope'] ?? null) : null, fn (Builder $query, string $scope) => $query->where('scope', $scope))
            ->when(in_array('gov', $only, true) ? ($search['gov'] ?? null) : null, fn (Builder $query, $gov) => $query->where('governorate_id', $gov))
            ->when(in_array('q', $only, true) ? ($search['q'] ?? null) : null, fn (Builder $query, string $needle) => $query->where(
                fn (Builder $inner) => $inner->where('title', 'like', "%{$needle}%")->orWhere('institution_name', 'like', "%{$needle}%")
            ));

        $clubs = $applyFilters($this->activeClubs()->with('governorate:id,name'), ['scope', 'gov', 'q'])
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        // La carte montre tous les gouvernorats concernés par la recherche (hors filtre gouvernorat),
        // pour pouvoir passer de l'un à l'autre ; le gouvernorat choisi est mis en évidence.
        $perGovernorate = $applyFilters($this->activeClubs()->whereNotNull('governorate_id'), ['scope', 'q'])
            ->selectRaw('governorate_id, count(*) as total')
            ->groupBy('governorate_id')
            ->pluck('total', 'governorate_id');

        $markers = Governorate::query()
            ->whereIn('id', $perGovernorate->keys())
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'name', 'latitude', 'longitude'])
            ->map(fn (Governorate $governorate): array => [
                'name' => $governorate->name,
                'lat' => $governorate->latitude,
                'lng' => $governorate->longitude,
                'count' => (int) $perGovernorate[$governorate->id],
                'active' => (int) ($filters['gov'] ?? 0) === $governorate->id,
                'url' => route('site.clubs', array_filter(['gov' => $governorate->id, 'scope' => $filters['scope'] ?? null, 'q' => $filters['q'] ?? null])),
            ])
            ->values()
            ->all();

        $clubScopes = $this->activeClubs()->whereNotNull('scope')->distinct()->pluck('scope')->all();

        return $this->render('site.clubs', [
            'clubs' => $clubs,
            'filters' => $filters,
            'markers' => $markers,
            'governorates' => Governorate::query()
                ->whereIn('id', $this->activeClubs()->whereNotNull('governorate_id')->select('governorate_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'scopes' => array_values(array_filter(OrgScopes::slugs(), fn (string $slug): bool => in_array($slug, $clubScopes, true))),
        ]);
    }

    public function contact(): View
    {
        return $this->render('site.contact', []);
    }

    public function contactStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'max:0'], // piege a robots : un humain ne voit pas ce champ
        ], [
            'required' => __('site.validation.required'),
            'email' => __('site.validation.email'),
            'min' => __('site.validation.min'),
            'max' => __('site.validation.max'),
        ], [
            'name' => __('site.contact.name'),
            'email' => __('site.contact.email'),
            'subject' => __('site.contact.subject'),
            'message' => __('site.contact.message'),
        ]);

        ContactMessage::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'locale' => app()->getLocale(),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('site.contact')->with('contact_sent', true);
    }

    /**
     * Piece jointe d'une publication publique (les autres restent reservees a l'application).
     */
    public function media(PostAttachment $attachment): StreamedResponse
    {
        $isPublic = $this->publicPosts()->whereKey($attachment->post_id)->exists();
        $disk = $attachment->disk ?: 'public';

        abort_unless($isPublic && Storage::disk($disk)->exists($attachment->path), 404);

        return Storage::disk($disk)->response($attachment->path, basename($attachment->path), [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $view, array $data): View
    {
        return view($view, $data + ['site' => new SiteSettings()]);
    }

    /**
     * Perimetre demande dans l'URL (?scope=university), ou null pour tout afficher.
     */
    private function scopeFilter(Request $request): ?string
    {
        return $request->validate(['scope' => ['nullable', Rule::in(OrgScopes::slugs())]])['scope'] ?? null;
    }

    /**
     * Restreint une liste a un perimetre. Un contenu sans perimetre est un contenu de la Fondation elle-meme.
     */
    private function inScope(Builder $query, ?string $scope): Builder
    {
        if ($scope === null) {
            return $query;
        }

        return $scope === 'foundation'
            ? $query->where(fn (Builder $inner) => $inner->whereNull('scope')->orWhere('scope', 'foundation'))
            : $query->where('scope', $scope);
    }

    private function publicPosts(): Builder
    {
        return Post::query()
            ->with(['author:id,name', 'attachments:id,post_id,disk,path,mime_type'])
            ->where('is_site_news', true)
            ->where('visibility', 'public')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->latest('id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, PostAttachment>
     */
    private function visibleAttachments(Post $post)
    {
        return $post->attachments->filter(
            fn (PostAttachment $attachment): bool => Storage::disk($attachment->disk ?: 'public')->exists($attachment->path)
                && (str_starts_with($attachment->mime_type, 'image/') || str_starts_with($attachment->mime_type, 'video/'))
        )->values();
    }

    private function publicEvents(): Builder
    {
        return FoundationEvent::query()
            ->whereIn('status', ['published', 'completed'])
            ->whereNotNull('starts_at');
    }

    private function upcomingEvents(): Builder
    {
        return FoundationEvent::query()
            ->where('status', 'published')
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at');
    }

    private function activeClubs(): Builder
    {
        return OrgNode::query()->where('kind', 'club')->where('status', 'active');
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'members' => Membership::query()->where('status', 'approved')->distinct()->count('user_id'),
            'clubs' => $this->activeClubs()->count(),
            'governorates' => $this->activeClubs()->whereNotNull('governorate_id')->distinct()->count('governorate_id'),
            'events' => $this->publicEvents()->count(),
        ];
    }
}
