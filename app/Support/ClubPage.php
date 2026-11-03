<?php

namespace App\Support;

use App\Models\ClubActivity;
use App\Models\ClubNews;
use App\Models\FoundationEvent;
use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\Challenges\Challenges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Page profil d'un club : chaque onglet calcule ses propres données, pour que seul l'onglet affiché soit chargé.
 */
final class ClubPage
{
    /** Onglet => [libellé, icône]. */
    public const TABS = [
        'apropos' => ['À propos', 'fa-regular fa-address-card'],
        'activites' => ['Activités', 'fa-regular fa-calendar'],
        'actualites' => ['Actualités', 'fa-regular fa-newspaper'],
        'organisation' => ['Organisation', 'fa-solid fa-sitemap'],
        'membres' => ['Membres', 'fa-solid fa-users'],
        'bureaux' => ['Bureaux', 'fa-solid fa-landmark'],
    ];

    private const DEPARTMENT_KINDS = ['club_department', 'unit', 'department'];

    private const MAX_ROWS = 300;

    private const MAX_OTHER_MEMBERS = 200;

    private const MAX_ITEMS = 20;

    private ?array $structure = null;

    private ?bool $canManage = null;

    public function __construct(private readonly OrgNode $club, private readonly User $user)
    {
    }

    public static function tab(?string $tab): string
    {
        return array_key_exists((string) $tab, self::TABS) ? (string) $tab : 'apropos';
    }

    /** Publier des actualités : droit de publication sur ce club (président, responsable), son périmètre, ou toute la plateforme. */
    public function canManage(): bool
    {
        if ($this->canManage !== null) {
            return $this->canManage;
        }

        $responsibility = OrgAccess::responsibility($this->user, 'post.publish_public');
        $scope = OrgAccess::scopeOf($this->club);

        return $this->canManage = $responsibility['all']
            || in_array($this->club->id, $responsibility['clubs'], true)
            || ($scope !== null && in_array($scope, $responsibility['scopes'], true));
    }

    /** Adhérent approuvé ou titulaire d'un poste du club. */
    public function isMember(): bool
    {
        if (Membership::query()->where('user_id', $this->user->id)->where('node_id', $this->club->id)->where('status', 'approved')->exists()) {
            return true;
        }

        return $this->currentAssignments($this->nodeIds())
            ->whereHas('person', fn (Builder $person) => $person->where('user_id', $this->user->id))
            ->exists();
    }

    /**
     * Bandeau de la page : coordonnées, ancienneté et compteurs.
     *
     * @return array<string, mixed>
     */
    public function hero(): array
    {
        $club = $this->club;
        $founded = $club->founded_on;
        $links = collect([
            ['Site web', 'fa-solid fa-globe', $club->website_url],
            ['Facebook', 'fa-brands fa-facebook', $club->facebook_url],
            ['Instagram', 'fa-brands fa-instagram', $club->instagram_url],
            ['LinkedIn', 'fa-brands fa-linkedin', $club->linkedin_url],
        ])->filter(fn (array $link): bool => preg_match('#^https?://#i', (string) $link[2]) === 1)->values()->all();

        return [
            'logo' => $club->logoUrl(),
            'cover' => $club->coverUrl(),
            'scope' => OrgScopes::label($club->scope),
            'governorate' => $club->governorate?->name,
            'location' => collect([$club->address, $club->governorate?->name])->filter()->unique()->implode(' · '),
            'founded_year' => $founded?->year,
            'age' => $founded !== null && $founded->lte(now()) ? max(0, (int) $founded->diffInYears(now())) : null,
            'status' => OrgNode::CLUB_STATUSES[$club->status] ?? $club->status,
            'links' => $links,
            'stats' => [
                'members' => $this->memberCount(),
                'activities' => $this->activityQuery()->count() + $this->eventQuery()->count(),
                'news' => $this->newsQuery()->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function panel(string $tab): array
    {
        return match ($tab) {
            'activites' => $this->activities(),
            'actualites' => $this->news(),
            'organisation' => $this->organisation(),
            'membres' => $this->members(),
            'bureaux' => $this->bureaux(),
            default => $this->about(),
        } + ['club' => $this->club, 'canManage' => $this->canManage()];
    }

    /** @return array<string, mixed> */
    private function about(): array
    {
        $club = $this->club;

        return [
            'description' => $club->description,
            'subtitle' => $club->subtitle,
            'founded' => $club->founded_on,
            'location' => collect([$club->address, $club->governorate?->name])->filter()->unique()->implode(' · '),
            'institution' => $club->institution_name,
            'institutionType' => OrgNode::INSTITUTION_TYPES[$club->institution_type] ?? null,
            'scope' => OrgScopes::label($club->scope),
            'status' => OrgNode::CLUB_STATUSES[$club->status] ?? $club->status,
            'charter' => (bool) $club->charter_signed,
            'email' => $club->email,
        ];
    }

    // ───────────────────────── Activités ─────────────────────────

    /** @return array<string, mixed> */
    private function activities(): array
    {
        $startOfDay = now()->startOfDay();
        $linked = Challenges::enabled();

        $items = collect();

        foreach ([['upcoming', true], ['past', false]] as [$when, $future]) {
            $activities = $this->activityQuery()
                ->where('starts_at', $future ? '>=' : '<', $startOfDay)
                ->orderBy('starts_at', $future ? 'asc' : 'desc')
                ->limit(self::MAX_ITEMS)
                ->get();

            $events = $this->eventQuery()
                ->where('starts_at', $future ? '>=' : '<', $startOfDay)
                ->orderBy('starts_at', $future ? 'asc' : 'desc')
                ->limit(self::MAX_ITEMS)
                ->get();

            $merged = $activities->map(fn (ClubActivity $activity): array => [
                'title' => $activity->title,
                'type' => $activity->typeLabel(),
                'starts_at' => $activity->starts_at,
                'location' => $activity->location,
                'cover' => $activity->coverUrl(),
                'url' => $linked ? route('activities.show', $activity) : null,
                'cancelled' => false,
            ])->concat($events->map(fn (FoundationEvent $event): array => [
                'title' => $event->title,
                'type' => 'Événement',
                'starts_at' => $event->starts_at,
                'location' => $event->location,
                'cover' => $event->coverUrl(),
                'url' => null,
                'cancelled' => false,
            ]));

            $items->put($when, ($future ? $merged->sortBy('starts_at') : $merged->sortByDesc('starts_at'))->take(self::MAX_ITEMS)->values());
        }

        return ['upcoming' => $items['upcoming'], 'past' => $items['past']];
    }

    /** @return Builder<ClubActivity> */
    private function activityQuery(): Builder
    {
        $audiences = ($this->canManage() || $this->isMember()) ? array_keys(ClubActivity::AUDIENCES) : ['public', 'tunimateurs'];

        return ClubActivity::query()
            ->where('status', 'published')
            ->whereHas('challenge', fn (Builder $challenge) => $challenge->where('status', 'published'))
            ->whereIn('audience', $audiences)
            ->where(fn (Builder $query) => $query
                ->where('club_id', $this->club->id)
                ->orWhereHas('organizers', fn (Builder $organizer) => $organizer->where('club_id', $this->club->id)->where('status', 'accepted')));
    }

    /** @return Builder<FoundationEvent> */
    private function eventQuery(): Builder
    {
        return FoundationEvent::query()
            ->where('node_id', $this->club->id)
            ->whereIn('status', ['published', 'completed'])
            ->whereNotNull('starts_at');
    }

    // ───────────────────────── Actualités ─────────────────────────

    /** @return array<string, mixed> */
    private function news(): array
    {
        return [
            'news' => $this->newsQuery()->with('author:id,name')->latest('published_at')->latest('id')->limit(self::MAX_ITEMS)->get(),
        ];
    }

    /** @return Builder<ClubNews> */
    private function newsQuery(): Builder
    {
        return ClubNews::query()->where('club_id', $this->club->id)->where('published_at', '<=', now());
    }

    // ───────────────────────── Organisation ─────────────────────────

    /** @return array<string, mixed> */
    private function organisation(): array
    {
        $structure = $this->structure();
        $positionIds = collect($structure['rows'])->filter(fn (array $row): bool => $row['node']->kind === 'position')->pluck('node.id')->all();
        $holders = $this->holders($positionIds)->groupBy('node_id');

        $chain = [];
        $parent = $this->club->parent;

        for ($guard = 0; $parent !== null && $guard < 10; $guard++) {
            array_unshift($chain, $parent);
            $parent = $parent->parent;
        }

        // Effectif par département : titulaires des postes placés sous ce département.
        $departments = [];

        foreach ($structure['rows'] as $row) {
            if (in_array($row['node']->kind, self::DEPARTMENT_KINDS, true)) {
                $departments[$row['node']->id] = ['node' => $row['node'], 'depth' => $row['depth'], 'people' => 0, 'positions' => 0];
            }
        }

        foreach ($structure['rows'] as $row) {
            $key = $row['dept'];

            if ($row['node']->kind === 'position' && $key !== null && isset($departments[$key])) {
                $departments[$key]['positions']++;
                $departments[$key]['people'] += $holders->get($row['node']->id, collect())->count();
            }
        }

        $siblings = $this->club->scope === null ? collect() : OrgNode::query()
            ->where('kind', 'club')->where('status', 'active')->where('scope', $this->club->scope)->whereKeyNot($this->club->id)
            ->with('institution')->orderBy('title')->limit(12)->get();

        return [
            'chain' => $chain,
            'scopeLabel' => OrgScopes::label($this->club->scope),
            'scope' => $this->club->scope,
            'subClubs' => $structure['subClubs'],
            'departments' => array_values($departments),
            'siblings' => $siblings,
        ];
    }

    // ───────────────────────── Membres ─────────────────────────

    /** @return array<string, mixed> */
    private function members(): array
    {
        $rows = $this->structure()['rows'];
        $positionIds = collect($rows)->filter(fn (array $row): bool => $row['node']->kind === 'position')->pluck('node.id')->all();
        $byNode = $this->holders($positionIds)->groupBy('node_id');

        $groups = [0 => ['department' => null, 'depth' => 0, 'people' => []]];
        $departmentDepth = [];

        foreach ($rows as $row) {
            $node = $row['node'];

            if (in_array($node->kind, self::DEPARTMENT_KINDS, true)) {
                $groups[$node->id] ??= ['department' => $node, 'depth' => $row['depth'], 'people' => []];
                $departmentDepth[$node->id] = $row['depth'];

                continue;
            }

            if ($node->kind !== 'position') {
                continue;
            }

            $key = $row['dept'] ?? 0;
            $level = max(0, $row['depth'] - ($key === 0 ? 0 : ($departmentDepth[$key] ?? 0) + 1));

            foreach ($byNode->get($node->id, collect()) as $assignment) {
                $person = $this->personRow($assignment->person);
                $id = ($person['user_id'] ?? $person['name']).'|'.$node->id;

                $groups[$key]['people'][$id] ??= $person + ['title' => $node->title, 'leader' => self::isLeader($node), 'level' => $level];
            }
        }

        $groups = array_values(array_filter(array_map(function (array $group): array {
            $group['people'] = array_values($group['people']);

            return $group;
        }, $groups), fn (array $group): bool => $group['people'] !== []));

        $placed = collect($groups)->flatMap(fn (array $group) => collect($group['people'])->pluck('user_id'))->filter()->unique();
        $others = User::query()
            ->whereIn('id', Membership::query()->where('node_id', $this->club->id)->where('status', 'approved')->select('user_id'))
            ->whereNotIn('id', $placed->all())
            ->where('is_active', true)
            ->with('profile:id,user_id,photo_path')
            ->orderBy('name')
            ->limit(self::MAX_OTHER_MEMBERS)
            ->get()
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'photo' => $user->profile?->photo_path ? '/storage/'.ltrim($user->profile->photo_path, '/') : null,
                'url' => route('member.show', $user),
                'user_id' => $user->id,
            ])->all();

        return [
            'groups' => $groups,
            'others' => $others,
            'total' => $this->memberCount(),
        ];
    }

    // ───────────────────────── Bureaux et histoire ─────────────────────────

    /** @return array<string, mixed> */
    private function bureaux(): array
    {
        $rows = $this->structure()['rows'];
        $leaders = collect($rows)->filter(fn (array $row): bool => $row['node']->kind === 'position' && self::isLeader($row['node']));
        $nodes = $leaders->mapWithKeys(fn (array $row): array => [$row['node']->id => $row['node']]);
        $order = $leaders->values()->pluck('node.id')->flip()->all();
        $leaderTitles = $nodes->map(fn (OrgNode $node): string => Str::lower($node->title))->values()->all();

        $entry = function (OrgNode $node, OrgPerson $person, bool $declared = false) use ($order): array {
            return $this->personRow($person) + [
                'title' => $node->title,
                'president' => self::isPresident($node->title, $node->access_level),
                'declared' => $declared,
                'order' => $order[$node->id] ?? 999,
            ];
        };

        $sort = fn (array $entries): array => collect($entries)
            ->unique(fn (array $row): string => ($row['user_id'] ?? $row['name']).'|'.Str::lower($row['title']))
            ->sortBy([['president', 'desc'], ['order', 'asc']])
            ->values()
            ->all();

        $all = $this->holders($nodes->keys()->all(), currentOnly: false);
        $today = now()->toDateString();

        $currentEntries = $sort($all
            ->filter(fn (OrgAssignment $assignment): bool => $assignment->ends_at === null || $assignment->ends_at->toDateString() >= $today)
            ->map(fn (OrgAssignment $assignment): array => $entry($nodes[$assignment->node_id], $assignment->person))
            ->all());

        $currentMandate = Mandate::query()->where('status', Mandate::STATUS_CURRENT)->orderByDesc('start_year')->first();

        $declared = MandateParticipation::query()
            ->where('node_id', $this->club->id)->where('status', 'approved')->whereNotNull('position_title')
            ->with('user.profile:id,user_id,photo_path')
            ->get()
            ->filter(fn (MandateParticipation $participation): bool => $participation->user?->is_active === true
                && (in_array(Str::lower((string) $participation->position_title), $leaderTitles, true) || self::isPresident((string) $participation->position_title, null)));

        $history = [];

        foreach (Mandate::query()->where('status', '!=', Mandate::STATUS_UPCOMING)->orderByDesc('start_year')->orderByDesc('id')->get() as $mandate) {
            $entries = $all
                ->filter(fn (OrgAssignment $assignment): bool => $this->inMandate($mandate, $assignment->starts_at, $assignment->ends_at, $currentMandate))
                ->map(fn (OrgAssignment $assignment): array => $entry($nodes[$assignment->node_id], $assignment->person))
                ->all();

            foreach ($declared->where('mandate_id', $mandate->id) as $participation) {
                $user = $participation->user;
                $entries[] = [
                    'name' => $user->name,
                    'photo' => $user->profile?->photo_path ? '/storage/'.ltrim($user->profile->photo_path, '/') : null,
                    'url' => route('member.show', $user),
                    'user_id' => $user->id,
                    'title' => (string) $participation->position_title,
                    'president' => self::isPresident((string) $participation->position_title, null),
                    'declared' => true,
                    'order' => 998,
                ];
            }

            if ($entries === []) {
                continue;
            }

            $entries = $sort($entries);
            $history[] = [
                'mandate' => $mandate,
                'current' => $currentMandate?->id === $mandate->id,
                'entries' => $entries,
                'president' => collect($entries)->firstWhere('president', true),
            ];
        }

        return [
            'currentMandate' => $currentMandate,
            'current' => $currentEntries,
            'history' => $history,
            'presidents' => collect($history)
                ->reject(fn (array $period): bool => $period['current'])
                ->filter(fn (array $period): bool => $period['president'] !== null)
                ->map(fn (array $period): array => ['mandate' => $period['mandate'], 'person' => $period['president']])
                ->values()
                ->all(),
        ];
    }

    /** Sans dates, un titulaire n'est rattaché qu'au mandat en cours ; avec une date de fin seule, au mandat qui la contient. */
    private function inMandate(Mandate $mandate, ?\Carbon\CarbonInterface $start, ?\Carbon\CarbonInterface $end, ?Mandate $current): bool
    {
        $windowStart = $mandate->windowStart();
        $windowEnd = $mandate->windowEnd();

        if ($start === null && $end === null) {
            return $current !== null && $current->id === $mandate->id;
        }

        if ($start === null) {
            return $end->gte($windowStart) && $end->lte($windowEnd);
        }

        return $start->lte($windowEnd) && ($end ?? now())->gte($windowStart);
    }

    // ───────────────────────── Aides communes ─────────────────────────

    /** Un poste de responsabilité porte des droits ou n'a qu'un titulaire (président, vice-président, trésorier…). */
    public static function isLeader(OrgNode $node): bool
    {
        return $node->access_level !== AccessLevels::NONE || $node->capacity !== 'multiple';
    }

    public static function isPresident(string $title, ?string $accessLevel): bool
    {
        return $accessLevel === 'club_president' || preg_match('/^\s*pr[ée]sident/iu', $title) === 1;
    }

    /**
     * Sous-arbre du club : départements et postes dans l'ordre de l'organigramme ; les clubs rattachés ont leur propre page.
     *
     * @return array{rows: array<int, array{node: OrgNode, depth: int, dept: int|null}>, subClubs: Collection<int, OrgNode>}
     */
    private function structure(): array
    {
        if ($this->structure !== null) {
            return $this->structure;
        }

        $children = OrgNode::query()
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'parent_id', 'kind', 'title', 'subtitle', 'capacity', 'access_level', 'status', 'logo_path', 'institution_id', 'sort_order'])
            ->groupBy('parent_id');

        $rows = [];
        $subClubs = collect();

        $walk = function (int $parentId, int $depth, ?int $dept) use (&$walk, &$rows, &$subClubs, $children): void {
            foreach ($children->get($parentId, collect()) as $child) {
                if (count($rows) >= self::MAX_ROWS || $depth > 12) {
                    return;
                }

                if ($child->kind === 'club') {
                    $subClubs->push($child);

                    continue;
                }

                if (! in_array($child->kind, ['position', ...self::DEPARTMENT_KINDS], true)) {
                    continue;
                }

                $rows[] = ['node' => $child, 'depth' => $depth, 'dept' => $dept];
                $walk((int) $child->id, $depth + 1, in_array($child->kind, self::DEPARTMENT_KINDS, true) ? (int) $child->id : $dept);
            }
        };

        $walk((int) $this->club->id, 0, null);

        return $this->structure = ['rows' => $rows, 'subClubs' => $subClubs];
    }

    /** @return array<int, int> */
    private function nodeIds(): array
    {
        return array_map(fn (array $row): int => (int) $row['node']->id, $this->structure()['rows']);
    }

    private function memberCount(): int
    {
        $users = Membership::query()->where('node_id', $this->club->id)->where('status', 'approved')->pluck('user_id');

        $people = $this->currentAssignments($this->nodeIds())->with('person:id,user_id')->get()->pluck('person')->filter();

        return $users->merge($people->pluck('user_id')->filter())->unique()->count()
            + $people->whereNull('user_id')->pluck('id')->unique()->count();
    }

    /** @return Builder<OrgAssignment> */
    private function currentAssignments(array $nodeIds): Builder
    {
        return OrgAssignment::query()
            ->whereIn('node_id', $nodeIds)
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()));
    }

    /**
     * Titulaires des postes donnés (en cours par défaut), sans les comptes désactivés.
     *
     * @param array<int, int> $nodeIds
     * @return Collection<int, OrgAssignment>
     */
    private function holders(array $nodeIds, bool $currentOnly = true): Collection
    {
        if ($nodeIds === []) {
            return collect();
        }

        $query = $currentOnly ? $this->currentAssignments($nodeIds) : OrgAssignment::query()->whereIn('node_id', $nodeIds);

        return $query
            ->with(['person.user.profile:id,user_id,photo_path'])
            ->orderBy('id')
            ->get()
            ->filter(fn (OrgAssignment $assignment): bool => $assignment->person !== null && ($assignment->person->user_id === null || $assignment->person->user?->is_active === true))
            ->values();
    }

    /** @return array{name: string, photo: string|null, url: string|null, user_id: int|null} */
    private function personRow(OrgPerson $person): array
    {
        $user = $person->user;

        return [
            'name' => $person->displayName(),
            'photo' => $person->photoUrl(),
            'url' => $user !== null && $user->is_active ? route('member.show', $user) : null,
            'user_id' => $person->user_id,
        ];
    }
}
