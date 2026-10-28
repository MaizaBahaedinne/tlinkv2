<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Comment;
use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\NewsletterSubscriber;
use App\Models\OrgNode;
use App\Models\Partner;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\SiteNews;
use App\Models\SiteProject;
use App\Support\OrgScopes;
use App\Support\PublicOrgChart;
use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Site public de la Fondation : ne lit que des donnees deja publiques dans T.Link
 * (actualites du site, evenements publies, postes de l'organigramme, clubs actifs).
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
        $news = SiteNews::query()->visible()->limit(3)->get();
        $events = $this->upcomingEvents()->limit(3)->get();

        return $this->render('site.home', [
            'stats' => $this->stats(),
            'news' => $news,
            'events' => $events,
            'partners' => Partner::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            ...$this->scopeTreeData(),
        ]);
    }

    public function about(): View
    {
        $groups = PublicOrgChart::teamByScope();

        return $this->render('site.about', [
            'stats' => $this->stats(),
            'spaces' => $this->spaces($groups),
            'president' => PublicOrgChart::president(),
        ]);
    }

    /**
     * Les espaces (périmètres) de la Fondation avec leur description, leur logo, leurs sous-espaces et leur équipe.
     *
     * @param array<string, \Illuminate\Support\Collection<int, OrgNode>> $groups
     * @return array<int, array{slug: string, name: string, description: string, logo: string|null, children: array<int, string>, team: \Illuminate\Support\Collection<int, OrgNode>}>
     */
    private function spaces(array $groups): array
    {
        $tree = $this->scopeTreeData();
        $names = $tree['scopes'];
        $childrenOf = [];

        foreach ($tree['scopeHierarchy'] as $child => $parent) {
            if ($parent !== null && isset($names[$child], $names[$parent])) {
                $childrenOf[$parent][] = $child;
            }
        }

        $translated = fn (string $key): ?string => \Illuminate\Support\Facades\Lang::has($key) ? (string) __($key) : null;
        $spaces = [];

        foreach ($names as $slug => $label) {
            $slug = (string) $slug;
            $description = trim((string) ($tree['scopeDescriptions'][$slug] ?? '')) ?: ($translated("site.scopes.{$slug}.text") ?? '');

            $spaces[] = [
                'slug' => $slug,
                'name' => $translated("site.scopes.{$slug}.name") ?? (string) $label,
                'description' => $description,
                'logo' => $tree['scopeLogos'][$slug] ?? null,
                'children' => array_map(fn (string $child): string => $translated("site.scopes.{$child}.name") ?? (string) $names[$child], $childrenOf[$slug] ?? []),
                'team' => $groups[$slug] ?? collect(),
            ];
        }

        return $spaces;
    }

    /**
     * @return array<string, mixed>
     */
    private function scopeTreeData(): array
    {
        return [
            'scopes' => OrgScopes::all(),
            'scopeHierarchy' => OrgScopes::hierarchy(),
            'scopeDescriptions' => OrgScopes::descriptions(),
            'scopeLogos' => collect(OrgScopes::logos())
                ->filter(fn (string $path) => Storage::disk('public')->exists($path))
                ->map(fn (string $path) => Storage::disk('public')->url($path))
                ->all(),
        ];
    }

    public function news(Request $request): View
    {
        $scope = $this->scopeFilter($request);

        return $this->render('site.news.index', [
            'news' => $this->inScope(SiteNews::query()->visible(), $scope)->paginate(9)->withQueryString(),
            'scope' => $scope,
        ]);
    }

    public function newsShow(int $news): View
    {
        $item = SiteNews::query()->visible()->with('feedPost')->findOrFail($news);

        // Autres articles de la même catégorie ; à défaut, les plus récents.
        $sameCategory = SiteNews::query()->visible()->inCategory($item->scope)->whereKeyNot($item->id)->limit(3)->get();

        return $this->render('site.news.show', [
            'item' => $item,
            'related' => $sameCategory->isNotEmpty() ? $sameCategory : SiteNews::query()->visible()->whereKeyNot($item->id)->limit(3)->get(),
            'relatedInCategory' => $sameCategory->isNotEmpty(),
            'feed' => $item->feedPost ? $this->feedActivity($item->feedPost) : null,
        ]);
    }

    /**
     * Réactions et commentaires des membres sur la publication du fil qui relaie l'article (lecture seule).
     *
     * @return array{reactions: array<string, int>, reactions_total: int, comments: \Illuminate\Support\Collection<int, Comment>, comments_count: int, comments_enabled: bool}
     */
    private function feedActivity(Post $post): array
    {
        $reactions = PostReaction::query()
            ->selectRaw('reaction_type, COUNT(*) as aggregate_count')
            ->where('post_id', $post->id)
            ->groupBy('reaction_type')
            ->pluck('aggregate_count', 'reaction_type')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return [
            'reactions' => $reactions,
            'reactions_total' => array_sum($reactions),
            'comments' => Comment::query()
                ->where('post_id', $post->id)
                ->whereNull('parent_id')
                ->with(['author:id,name', 'replies.author:id,name'])
                ->orderBy('created_at')
                ->limit(30)
                ->get(),
            'comments_count' => Comment::query()->where('post_id', $post->id)->count(),
            'comments_enabled' => (bool) $post->comments_enabled,
        ];
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

    public function projects(): View
    {
        return $this->render('site.projects.index', ['projects' => SiteProject::publicPages()]);
    }

    public function projectShow(string $slug): View
    {
        $pages = SiteProject::publicPages();
        $project = $pages->firstWhere('scope', $slug);

        abort_if($project === null, 404);

        return $this->render('site.projects.show', [
            'project' => $project,
            'others' => $pages->where('scope', '!=', $slug)->take(3)->values(),
        ]);
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

    public function newsletterStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'max:0'], // piège à robots
        ], [
            'required' => __('site.validation.required'),
            'email' => __('site.validation.email'),
            'max' => __('site.validation.max'),
        ], ['email' => __('site.contact.email')]);

        $email = mb_strtolower(trim($data['email']));

        try {
            $subscriber = NewsletterSubscriber::query()->firstOrCreate(['email' => $email], [
                'locale' => app()->getLocale(),
                'ip_address' => $request->ip(),
                'subscribed_at' => now(),
            ]);

            // Une personne désinscrite qui se réinscrit elle-même redevient abonnée.
            if ($subscriber->unsubscribed_at !== null) {
                $subscriber->update(['unsubscribed_at' => null, 'subscribed_at' => now(), 'locale' => app()->getLocale()]);
            }
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Double envoi simultané : l'adresse est déjà inscrite.
        }

        // Même réponse que l'adresse soit nouvelle ou déjà connue : on ne révèle pas qui est abonné.
        return redirect(route('site.about').'#newsletter')->with('newsletter_sent', true);
    }

    public function newsletterUnsubscribe(string $token): View
    {
        $subscriber = NewsletterSubscriber::query()->where('unsubscribe_token', $token)->firstOrFail();

        return $this->render('site.newsletter-unsubscribe', ['token' => $subscriber->unsubscribe_token, 'done' => $subscriber->unsubscribed_at !== null]);
    }

    public function newsletterUnsubscribeConfirm(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::query()->where('unsubscribe_token', $token)->firstOrFail();

        if ($subscriber->unsubscribed_at === null) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return redirect()->route('site.newsletter.unsubscribe', ['token' => $token]);
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

    private function publicEvents(): Builder
    {
        return FoundationEvent::query()
            ->with('governorate')
            ->whereIn('status', ['published', 'completed'])
            ->whereNotNull('starts_at');
    }

    private function upcomingEvents(): Builder
    {
        return FoundationEvent::query()
            ->with('governorate')
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
