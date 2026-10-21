<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Support\OrgScopes;
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
    /** Categories de l'organigramme presentees sur la page « Equipe ». */
    private const TEAM_CATEGORIES = ['presidency', 'direction', 'national_office'];

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
            'scopes' => OrgScopes::ALL,
        ]);
    }

    public function about(): View
    {
        return $this->render('site.about', [
            'stats' => $this->stats(),
            'scopes' => OrgScopes::ALL,
        ]);
    }

    public function news(): View
    {
        return $this->render('site.news.index', [
            'posts' => $this->publicPosts()->paginate(9),
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

    public function events(): View
    {
        return $this->render('site.events.index', [
            'upcoming' => $this->upcomingEvents()->get(),
            'past' => $this->publicEvents()
                ->where('starts_at', '<', now()->startOfDay())
                ->orderByDesc('starts_at')
                ->paginate(9),
        ]);
    }

    public function eventShow(int $event): View
    {
        $event = $this->publicEvents()->findOrFail($event);

        return $this->render('site.events.show', ['event' => $event]);
    }

    public function team(): View
    {
        $today = now()->startOfDay();

        $nodes = OrgNode::query()
            ->whereIn('category', self::TEAM_CATEGORIES)
            ->where('validation_status', 'confirmed')
            // Les administrateurs techniques de la plateforme ne sont pas presentes au public.
            ->where(fn (Builder $query) => $query->whereNull('system_key')->orWhere('system_key', '!=', 'national-administrators'))
            ->with(['assignments.person.user.profile'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (OrgNode $node) use ($today): OrgNode {
                $node->setRelation('assignments', $node->assignments->filter(
                    fn (OrgAssignment $assignment): bool => ($assignment->starts_at === null || $assignment->starts_at <= $today)
                        && ($assignment->ends_at === null || $assignment->ends_at >= $today)
                )->values());

                return $node;
            })
            ->filter(fn (OrgNode $node): bool => $node->assignments->isNotEmpty())
            ->groupBy('category');

        return $this->render('site.team', [
            'groups' => collect(self::TEAM_CATEGORIES)->filter(fn (string $category) => $nodes->has($category))
                ->mapWithKeys(fn (string $category) => [$category => $nodes->get($category)]),
        ]);
    }

    public function clubs(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(OrgScopes::CLUB_BRANCHES))],
            'gov' => ['nullable', 'integer', 'exists:governorates,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $clubs = $this->activeClubs()
            ->with('governorate:id,name')
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('scope', $type))
            ->when($filters['gov'] ?? null, fn (Builder $query, $gov) => $query->where('governorate_id', $gov))
            ->when($filters['q'] ?? null, fn (Builder $query, string $needle) => $query->where(
                fn (Builder $inner) => $inner->where('title', 'like', "%{$needle}%")->orWhere('institution_name', 'like', "%{$needle}%")
            ))
            ->orderBy('title')
            ->paginate(24)
            ->withQueryString();

        return $this->render('site.clubs', [
            'clubs' => $clubs,
            'filters' => $filters,
            'governorates' => Governorate::query()
                ->whereIn('id', $this->activeClubs()->whereNotNull('governorate_id')->select('governorate_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'types' => array_keys(OrgScopes::CLUB_BRANCHES),
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

    private function publicPosts(): Builder
    {
        return Post::query()
            ->with(['author:id,name', 'attachments:id,post_id,disk,path,mime_type'])
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
