<?php

namespace App\Http\Controllers;

use App\Models\FoundationEvent;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Recherche de la barre de navigation T.Link : membres de l'organigramme, structures (clubs, bureaux, postes)
 * et événements publiés. Aucune coordonnée (e-mail, téléphone) n'est renvoyée.
 */
class SearchController extends Controller
{
    private const MIN_LENGTH = 2;

    public function index(Request $request): View
    {
        $term = $this->term($request);

        return view('search.index', [
            'term' => $term,
            'groups' => $this->search($term, 20),
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $term = $this->term($request);

        return response()->json([
            'term' => $term,
            'groups' => $this->search($term, 4),
            'all_url' => route('search.index', ['q' => $term]),
        ]);
    }

    private function term(Request $request): string
    {
        $request->validate(['q' => ['nullable', 'string', 'max:80']]);

        return trim((string) $request->query('q', ''));
    }

    /**
     * @return array<int, array{key: string, label: string, items: array<int, array<string, mixed>>}>
     */
    private function search(string $term, int $limit): array
    {
        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [
            ['key' => 'people', 'label' => 'Membres', 'items' => $this->people($term, $limit)],
            ['key' => 'structures', 'label' => 'Structures', 'items' => $this->structures($term, $limit)],
            ['key' => 'events', 'label' => 'Événements', 'items' => $this->events($term, $limit)],
        ];

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }

    /** Langue du site vitrine pour les liens vers ses pages (hors des routes localisées, aucune par défaut). */
    private function siteLocale(): string
    {
        $locale = app()->getLocale();

        return array_key_exists($locale, config('site.locales')) ? $locale : config('site.default_locale');
    }

    /** Tous les mots saisis (4 au plus) doivent correspondre, chacun dans l'un des champs. */
    private function words(string $term): array
    {
        $words = array_slice(preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY), 0, 4);

        return array_map(fn (string $word): string => '%'.addcslashes($word, '\\%_').'%', $words);
    }

    private function people(string $term, int $limit): array
    {
        $query = OrgPerson::query()
            ->with(['user:id,name', 'user.profile:id,user_id,photo_path', 'assignments.node:id,title'])
            ->whereHas('assignments')
            ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhereHas('user', fn (Builder $u) => $u->where('is_active', true)));

        foreach ($this->words($term) as $like) {
            $query->where(fn (Builder $q) => $q
                ->where('full_name', 'like', $like)
                ->orWhere('job_title', 'like', $like)
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)));
        }

        return $query->limit($limit)->get()
            ->sortBy(fn (OrgPerson $person): string => mb_strtolower($person->displayName()))
            ->values()
            ->map(function (OrgPerson $person): array {
                $node = $person->assignments->first()?->node;

                return [
                    'title' => $person->displayName(),
                    'subtitle' => collect([$person->job_title, $node?->title])->filter()->unique()->implode(' · ') ?: null,
                    'image' => $person->photoUrl(),
                    'url' => $person->user_id
                        ? route('member.show', $person->user_id)
                        : ($node ? route('orgchart.index', ['bloc' => $node->id]) : route('orgchart.index')),
                ];
            })
            ->all();
    }

    private function structures(string $term, int $limit): array
    {
        $query = OrgNode::query()
            ->with('governorate:id,name')
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', 'active'));

        foreach ($this->words($term) as $like) {
            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', $like)
                ->orWhere('subtitle', 'like', $like)
                ->orWhereHas('governorate', fn (Builder $g) => $g->where('name', 'like', $like)));
        }

        return $query->orderBy('title')->limit($limit)->get()
            ->map(fn (OrgNode $node): array => [
                'title' => $node->title,
                'subtitle' => collect([OrgNode::KINDS[$node->kind] ?? null, $node->governorate?->name])->filter()->implode(' · ') ?: null,
                'image' => null,
                'url' => route('orgchart.index', ['bloc' => $node->id]),
            ])
            ->all();
    }

    private function events(string $term, int $limit): array
    {
        $query = FoundationEvent::query()
            ->with('governorate:id,name')
            ->whereIn('status', ['published', 'completed'])
            ->whereNotNull('starts_at');

        foreach ($this->words($term) as $like) {
            $query->where(fn (Builder $q) => $q
                ->where('title', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhereHas('governorate', fn (Builder $g) => $g->where('name', 'like', $like)));
        }

        return $query->orderByDesc('starts_at')->limit($limit)->get()
            ->map(fn (FoundationEvent $event): array => [
                'title' => $event->title,
                'subtitle' => collect([$event->starts_at->translatedFormat('d M Y'), $event->location, $event->governorate?->name])->filter()->implode(' · '),
                'image' => $event->coverUrl(),
                'url' => route('site.events.show', ['locale' => $this->siteLocale(), 'event' => $event->id]),
            ])
            ->all();
    }
}
