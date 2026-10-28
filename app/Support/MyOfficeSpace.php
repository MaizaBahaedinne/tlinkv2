<?php

namespace App\Support;

use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Volet « adhésions, clubs, événements et parcours » de la page « Mon bureau » :
 * ce que le membre est aujourd'hui (adhésions et mandats en cours), son club avec sa hiérarchie,
 * les événements passés et à venir, et son parcours (adhésions, mandats et participations).
 */
final class MyOfficeSpace
{
    private const MAX_BUREAU_ROWS = 200;

    private const MEMBERSHIP_LABELS = [
        'approved' => 'Adhérent(e)',
        'pending' => 'En attente de validation',
        'rejected' => 'Refusée',
        'suspended' => 'Suspendue',
        'archived' => 'Archivée',
    ];

    private const REGISTRATION_LABELS = [
        'pending' => 'Inscription en attente',
        'confirmed' => 'Inscrit(e)',
        'waitlisted' => 'Liste d\'attente',
        'attended' => 'Présent(e)',
        'absent' => 'Absent(e)',
        'rejected' => 'Refusée',
        'cancelled' => 'Annulée',
    ];

    /** Le membre a-t-il une adhésion ou une inscription à un événement (en dehors des postes) ? */
    public static function hasActivity(User $user): bool
    {
        return Membership::query()->where('user_id', $user->id)->exists()
            || EventRegistration::query()->where('user_id', $user->id)->exists();
    }

    /**
     * @return array{memberships: array<int, array<string, mixed>>, mandates: array<int, array<string, mixed>>, clubs: array<int, array<string, mixed>>, upcoming: Collection<int, FoundationEvent>, past: Collection<int, FoundationEvent>, registrations: array<int, string>, journey: array<int, array<string, mixed>>}
     */
    public static function build(User $user): array
    {
        $all = OrgNode::query()->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');
        $today = now()->toDateString();

        $memberships = Membership::query()->where('user_id', $user->id)->orderByDesc('starts_at')->orderByDesc('id')->get();
        $assignments = OrgAssignment::query()
            ->whereHas('person', fn (Builder $query) => $query->where('user_id', $user->id))
            ->whereHas('node', fn (Builder $query) => $query->where('kind', 'position'))
            ->get();

        $membershipRows = $memberships->map(function (Membership $membership) use ($all, $today): array {
            $node = $membership->node_id ? ($all[$membership->node_id] ?? null) : null;
            $club = $node !== null ? self::clubOf($node, $all) : null;
            $current = in_array($membership->status, ['approved', 'pending'], true)
                && ($membership->ends_at === null || $membership->ends_at->toDateString() >= $today);

            return [
                'membership' => $membership,
                'place' => ($club ?? $node)?->title ?? (OrgScopes::label($membership->scope) ?: 'Fondation'),
                'club' => $club,
                'label' => self::MEMBERSHIP_LABELS[$membership->status] ?? $membership->status,
                'current' => $current,
            ];
        })->all();

        $mandateRows = $assignments->map(function (OrgAssignment $assignment) use ($all, $today): array {
            $node = $all[$assignment->node_id] ?? null;
            $club = $node !== null ? self::clubOf($node, $all) : null;
            $current = $assignment->ends_at === null || $assignment->ends_at->toDateString() >= $today;

            return ['assignment' => $assignment, 'node' => $node, 'club' => $club, 'current' => $current];
        })->filter(fn (array $row): bool => $row['node'] !== null)->values()->all();

        // Mes clubs : ceux de mes adhésions en cours et ceux où j'occupe un poste en cours.
        $clubIds = [];

        foreach ($membershipRows as $row) {
            if ($row['current'] && $row['club'] !== null) {
                $clubIds[$row['club']->id] = true;
            }
        }

        foreach ($mandateRows as $row) {
            if ($row['current'] && $row['club'] !== null) {
                $clubIds[$row['club']->id] = true;
            }
        }

        $clubs = self::clubs(array_keys($clubIds), $all, $user, $membershipRows, $mandateRows);

        [$upcoming, $past, $registrations] = self::events(array_keys($clubIds), $user);

        return [
            'memberships' => array_values(array_filter($membershipRows, fn (array $row): bool => $row['current'])),
            'mandates' => array_values(array_filter($mandateRows, fn (array $row): bool => $row['current'])),
            'clubs' => $clubs,
            'upcoming' => $upcoming,
            'past' => $past,
            'registrations' => $registrations,
            'journey' => self::journey($membershipRows, $mandateRows, $user),
        ];
    }

    /** Libellé d'une inscription à un événement. */
    public static function registrationLabel(?string $status): ?string
    {
        return $status === null ? null : (self::REGISTRATION_LABELS[$status] ?? $status);
    }

    /**
     * @param array<int, int> $clubIds
     * @param \Illuminate\Support\Collection<int, OrgNode> $all
     * @param array<int, array<string, mixed>> $membershipRows
     * @param array<int, array<string, mixed>> $mandateRows
     * @return array<int, array<string, mixed>>
     */
    private static function clubs(array $clubIds, $all, User $user, array $membershipRows, array $mandateRows): array
    {
        if ($clubIds === []) {
            return [];
        }

        $children = [];

        foreach ($all as $node) {
            if ($node->parent_id !== null) {
                $children[$node->parent_id][] = $node->id;
            }
        }

        $plans = [];
        $needed = [];

        foreach ($clubIds as $clubId) {
            $club = $all[$clubId] ?? null;

            if ($club === null) {
                continue;
            }

            $rows = [];
            self::walkClub($clubId, 0, $children, $all, $rows);

            foreach ($rows as $row) {
                if ($row['node']->kind === 'position') {
                    $needed[$row['node']->id] = true;
                }
            }

            $plans[] = ['club' => $club, 'rows' => $rows];
        }

        $holders = MyOffice::holders(array_keys($needed), $user);
        $result = [];

        foreach ($plans as $plan) {
            $club = $plan['club'];

            $result[] = [
                'node' => $club,
                'governorate' => $club->governorate?->name,
                'membership' => collect($membershipRows)->first(fn (array $row): bool => $row['current'] && $row['club']?->id === $club->id)['label'] ?? null,
                'my_roles' => collect($mandateRows)->filter(fn (array $row): bool => $row['current'] && $row['club']?->id === $club->id)->map(fn (array $row): string => $row['node']->title)->values()->all(),
                'bureau' => array_map(fn (array $row): array => [
                    'node' => $row['node'],
                    'depth' => $row['depth'],
                    'holders' => $row['node']->kind === 'position' ? ($holders[$row['node']->id] ?? []) : [],
                ], $plan['rows']),
            ];
        }

        return $result;
    }

    /**
     * Sous-arbre d'un club : départements et postes, dans l'ordre de l'organigramme.
     *
     * @param array<int, array<int, int>> $children
     * @param \Illuminate\Support\Collection<int, OrgNode> $all
     * @param array<int, array{node: OrgNode, depth: int}> $rows
     */
    private static function walkClub(int $nodeId, int $depth, array $children, $all, array &$rows): void
    {
        foreach ($children[$nodeId] ?? [] as $childId) {
            $child = $all[$childId] ?? null;

            if ($child === null || count($rows) >= self::MAX_BUREAU_ROWS) {
                return;
            }

            if (! in_array($child->kind, ['position', 'club_department', 'unit', 'department'], true)) {
                continue;
            }

            $rows[] = ['node' => $child, 'depth' => $depth];
            self::walkClub($childId, $depth + 1, $children, $all, $rows);
        }
    }

    /**
     * Événements de mes clubs et ceux auxquels je suis inscrit(e).
     *
     * @param array<int, int> $clubIds
     * @return array{0: Collection<int, FoundationEvent>, 1: Collection<int, FoundationEvent>, 2: array<int, string>}
     */
    private static function events(array $clubIds, User $user): array
    {
        $registrations = EventRegistration::query()->where('user_id', $user->id)->pluck('status', 'event_id')->all();

        $events = FoundationEvent::query()
            ->with('governorate')
            ->whereNotNull('starts_at')
            ->where(function (Builder $query) use ($clubIds, $registrations): void {
                $query->whereIn('id', array_keys($registrations));

                if ($clubIds !== []) {
                    $query->orWhere(fn (Builder $inner) => $inner->whereIn('node_id', $clubIds)->whereIn('status', ['published', 'completed']));
                }
            })
            ->get()
            ->reject(fn (FoundationEvent $event): bool => ! isset($registrations[$event->id]) && ! in_array($event->status, ['published', 'completed'], true));

        $startOfDay = now()->startOfDay();

        return [
            $events->filter(fn (FoundationEvent $event): bool => $event->starts_at->gte($startOfDay))->sortBy('starts_at')->values(),
            $events->filter(fn (FoundationEvent $event): bool => $event->starts_at->lt($startOfDay))->sortByDesc('starts_at')->take(12)->values(),
            $registrations,
        ];
    }

    /**
     * Parcours : adhésions, mandats et participations à des événements passés, du plus récent au plus ancien.
     *
     * @param array<int, array<string, mixed>> $membershipRows
     * @param array<int, array<string, mixed>> $mandateRows
     * @return array<int, array{kind: string, icon: string, title: string, place: string|null, status: string, from: \Illuminate\Support\Carbon|null, to: \Illuminate\Support\Carbon|null, ongoing: bool, url: string|null}>
     */
    private static function journey(array $membershipRows, array $mandateRows, User $user): array
    {
        $items = [];

        foreach ($membershipRows as $row) {
            $membership = $row['membership'];

            $items[] = [
                'kind' => 'membership',
                'icon' => 'fa-solid fa-id-card',
                'title' => 'Adhésion · '.$row['place'],
                'place' => OrgScopes::label($membership->scope) ?: null,
                'status' => $row['label'],
                'from' => $membership->starts_at ?? $membership->created_at,
                'to' => $membership->ends_at,
                'ongoing' => $row['current'],
                'url' => null,
            ];
        }

        foreach ($mandateRows as $row) {
            $assignment = $row['assignment'];

            $items[] = [
                'kind' => 'mandate',
                'icon' => 'fa-solid fa-briefcase',
                'title' => 'Mandat · '.$row['node']->title,
                'place' => $row['club']?->title,
                'status' => $row['current'] ? 'En cours' : 'Terminé',
                'from' => $assignment->starts_at ?? $assignment->created_at,
                'to' => $assignment->ends_at,
                'ongoing' => $row['current'],
                'url' => null,
            ];
        }

        $locale = app()->getLocale();
        $locale = array_key_exists($locale, config('site.locales')) ? $locale : config('site.default_locale');
        $startOfDay = now()->startOfDay();

        EventRegistration::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->with('event')
            ->get()
            ->filter(fn (EventRegistration $registration): bool => $registration->event?->starts_at !== null && $registration->event->starts_at->lt($startOfDay))
            ->each(function (EventRegistration $registration) use (&$items, $locale): void {
                $event = $registration->event;

                $items[] = [
                    'kind' => 'event',
                    'icon' => 'fa-solid fa-calendar-check',
                    'title' => 'Événement · '.$event->title,
                    'place' => $event->location,
                    'status' => self::registrationLabel($registration->status) ?? '',
                    'from' => $event->starts_at,
                    'to' => null,
                    'ongoing' => false,
                    'url' => in_array($event->status, ['published', 'completed'], true) ? route('site.events.show', ['locale' => $locale, 'event' => $event->id]) : null,
                ];
            });

        usort($items, fn (array $a, array $b): int => ($b['from']?->getTimestamp() ?? 0) <=> ($a['from']?->getTimestamp() ?? 0));

        return $items;
    }

    /**
     * Club auquel appartient un bloc : lui-même s'il en est un, sinon son plus proche parent qui en est un.
     *
     * @param \Illuminate\Support\Collection<int, OrgNode> $all
     */
    private static function clubOf(OrgNode $node, $all): ?OrgNode
    {
        $current = $node;

        for ($guard = 0; $guard < 80 && $current !== null; $guard++) {
            if ($current->kind === 'club') {
                return $current;
            }

            $current = $current->parent_id !== null ? ($all[$current->parent_id] ?? null) : null;
        }

        return null;
    }
}
