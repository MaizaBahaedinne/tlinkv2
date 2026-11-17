<?php

namespace App\Support;

use App\Models\AcademyEnrollment;
use App\Models\AcademySession;
use App\Models\ActivityRegistration;
use App\Models\ActivityTask;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Academy\AcademyAccess;
use App\Support\Challenges\Challenges;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calendrier de « Mon bureau » : tâches, formations, événements, activités et réunions du membre, sur un mois.
 * Chaque entrée dit ce que c'est (kind), quand, et où aller pour la consulter.
 */
final class MyCalendar
{
    /** Types d'entrées, avec leur couleur : classes complètes (lues telles quelles par Tailwind). */
    public const KINDS = [
        'task' => ['label' => 'Tâches', 'icon' => 'fa-solid fa-list-check', 'chip' => 'bg-amber-100 text-amber-900', 'dot' => 'bg-amber-500', 'soft' => 'bg-amber-50 text-amber-700'],
        'training' => ['label' => 'Formations', 'icon' => 'fa-solid fa-graduation-cap', 'chip' => 'bg-violet-100 text-violet-900', 'dot' => 'bg-violet-500', 'soft' => 'bg-violet-50 text-violet-700'],
        'event' => ['label' => 'Événements', 'icon' => 'fa-solid fa-calendar-days', 'chip' => 'bg-rose-100 text-rose-900', 'dot' => 'bg-rose-500', 'soft' => 'bg-rose-50 text-rose-700'],
        'activity' => ['label' => 'Activités', 'icon' => 'fa-solid fa-people-group', 'chip' => 'bg-emerald-100 text-emerald-900', 'dot' => 'bg-emerald-500', 'soft' => 'bg-emerald-50 text-emerald-700'],
        'meeting' => ['label' => 'Réunions', 'icon' => 'fa-solid fa-handshake', 'chip' => 'bg-sky-100 text-sky-900', 'dot' => 'bg-sky-500', 'soft' => 'bg-sky-50 text-sky-700'],
    ];

    /** Jours affichés au plus pour une entrée qui s'étend sur plusieurs jours. */
    private const MAX_SPAN_DAYS = 31;

    private const UPCOMING_DAYS = 30;

    private const UPCOMING_LIMIT = 6;

    /** « 2026-11 » valide, sinon le mois en cours. */
    public static function parseMonth(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $value, $m) === 1) {
            return Carbon::create((int) $m[1], (int) $m[2], 1)->startOfDay();
        }

        return now()->startOfMonth();
    }

    /**
     * @param  array<int, int>  $clubIds  clubs du membre : leurs événements publiés apparaissent même sans inscription
     * @return array<string, mixed>
     */
    public static function month(User $user, Carbon $month, array $clubIds = []): array
    {
        $first = $month->copy()->startOfMonth();
        $last = $month->copy()->endOfMonth();
        $gridStart = $first->copy()->startOfWeek(CarbonInterface::MONDAY);
        $gridEnd = $last->copy()->endOfWeek(CarbonInterface::SUNDAY);

        $placed = self::placeByDay(self::entries($user, $gridStart, $gridEnd, $clubIds), $gridStart, $gridEnd);

        $weeks = [];
        $agenda = [];
        $counts = array_fill_keys(array_keys(self::KINDS), 0);
        $today = now()->toDateString();

        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $key = $day->toDateString();
            $entries = $placed[$key] ?? [];
            $inMonth = $day->betweenIncluded($first, $last);

            $weeks[intdiv((int) $gridStart->diffInDays($day), 7)][] = [
                'date' => $day->copy(),
                'in_month' => $inMonth,
                'today' => $key === $today,
                'entries' => $entries,
            ];

            if ($inMonth && $entries !== []) {
                $agenda[$key] = ['date' => $day->copy(), 'entries' => $entries];

                foreach ($entries as $entry) {
                    if ($entry['first_day']) {
                        $counts[$entry['kind']]++;
                    }
                }
            }
        }

        $from = now()->startOfDay();
        $upcoming = self::placeByDay(self::entries($user, $from, $from->copy()->addDays(self::UPCOMING_DAYS)->endOfDay(), $clubIds), $from, $from->copy()->addDays(self::UPCOMING_DAYS));
        $upcoming = collect($upcoming)->flatten(1)->filter(fn (array $entry): bool => $entry['first_day'] && $entry['state'] !== 'done')->take(self::UPCOMING_LIMIT)->values()->all();

        return [
            'month' => $first,
            'prev' => $first->copy()->subMonth()->format('Y-m'),
            'next' => $first->copy()->addMonth()->format('Y-m'),
            'is_current' => $first->isSameMonth(now()),
            'weeks' => array_values($weeks),
            'agenda' => $agenda,
            'counts' => $counts,
            'total' => array_sum($counts),
            'upcoming' => $upcoming,
        ];
    }

    /**
     * Toutes les entrées du membre qui touchent la période, triées par date.
     *
     * @param  array<int, int>  $clubIds
     * @return Collection<int, array<string, mixed>>
     */
    private static function entries(User $user, Carbon $from, Carbon $to, array $clubIds): Collection
    {
        return collect()
            ->concat(self::tasks($user, $from, $to))
            ->concat(self::sessions($user, $from, $to))
            ->concat(self::events($user, $from, $to, $clubIds))
            ->concat(self::activities($user, $from, $to))
            ->concat(self::meetings($user, $from, $to))
            ->sortBy(fn (array $entry): int => $entry['at']->getTimestamp())
            ->values();
    }

    /** Tâches d'activité qui me sont confiées, à leur échéance (journée entière). */
    private static function tasks(User $user, Carbon $from, Carbon $to): Collection
    {
        return ActivityTask::query()
            ->whereHas('assignees', fn ($query) => $query->whereKey($user->id))
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from->toDateString(), $to->toDateString()])
            ->with('activity:id,title')
            ->get()
            ->map(fn (ActivityTask $task): array => self::entry('task', $task->label, $task->activity?->title ? 'Activité : '.$task->activity->title : $task->teamLabel(), $task->due_at->copy()->startOfDay(), null, true, route('tasks.mine'), match (true) {
                $task->isDone() => 'done',
                $task->isLate() => 'late',
                default => null,
            }, $task->statusLabel()));
    }

    /** Sessions en direct d'Academy des cours auxquels je suis inscrit(e) (ou inclus dans mon abonnement). */
    private static function sessions(User $user, Carbon $from, Carbon $to): Collection
    {
        $courseIds = AcademyEnrollment::query()->where('user_id', $user->id)->pluck('course_id');

        if (AcademyAccess::hasSubscription($user)) {
            $courseIds = $courseIds->merge(\App\Models\AcademyCourse::query()->where('status', 'published')->where('included_in_plans', true)->pluck('id'));
        }

        if ($courseIds->isEmpty()) {
            return collect();
        }

        $reachable = Domains::reachableFrom('app', 'academy');

        return self::overlapping(AcademySession::query()->where('is_published', true)->whereIn('course_id', $courseIds->unique()), $from, $to)
            ->with('course:id,title,slug')
            ->get()
            ->map(fn (AcademySession $session): array => self::entry(
                'training',
                $session->title,
                trim('Academy'.($session->course ? ' · '.$session->course->title : '').($session->location ? ' · '.$session->location : '')),
                $session->starts_at,
                $session->ends_at,
                false,
                $reachable && $session->course ? route('academy.courses.show', $session->course) : null,
                null,
                'Session en direct',
            ));
    }

    /** Événements de la Fondation auxquels je suis inscrit(e) et ceux, publiés, de mes clubs. */
    private static function events(User $user, Carbon $from, Carbon $to, array $clubIds): Collection
    {
        $registrations = EventRegistration::query()->where('user_id', $user->id)->whereNotIn('status', ['rejected', 'cancelled'])->pluck('status', 'event_id');
        $locale = array_key_exists(app()->getLocale(), config('site.locales')) ? app()->getLocale() : config('site.default_locale');

        $query = FoundationEvent::query()
            ->whereIn('status', ['published', 'completed'])
            ->where(function (Builder $inner) use ($registrations, $clubIds): void {
                $inner->whereIn('id', $registrations->keys());

                if ($clubIds !== []) {
                    $inner->orWhereIn('node_id', $clubIds);
                }
            });

        return self::overlapping($query, $from, $to)->get()->map(fn (FoundationEvent $event): array => self::entry(
            'event',
            $event->title,
            (string) $event->location,
            $event->starts_at,
            $event->ends_at,
            false,
            route('site.events.show', ['locale' => $locale, 'event' => $event->id]),
            null,
            MyOfficeSpace::registrationLabel($registrations[$event->id] ?? null),
        ));
    }

    /** Activités de clubs où je suis inscrit(e) ; les formations / workshops comptent comme des formations. */
    private static function activities(User $user, Carbon $from, Carbon $to): Collection
    {
        $linkable = Challenges::enabled();

        return ActivityRegistration::query()
            ->where('user_id', $user->id)
            ->where('status', 'registered')
            ->whereHas('activity', fn (Builder $query) => self::overlapping($query->where('status', 'published'), $from, $to))
            ->with('activity.club:id,title')
            ->get()
            ->map(function (ActivityRegistration $registration) use ($linkable): array {
                $activity = $registration->activity;

                return self::entry(
                    $activity->type === 'training' ? 'training' : 'activity',
                    $activity->title,
                    trim(($activity->club?->title ?? '').($activity->location ? ' · '.$activity->location : ''), ' ·'),
                    $activity->starts_at,
                    $activity->ends_at,
                    false,
                    $linkable ? route('activities.show', $activity) : null,
                    null,
                    $registration->checked_in_at ? 'Présent(e)' : 'Inscrit(e)',
                );
            });
    }

    /** Réunions auxquelles je suis invité(e), sauf annulées ou déclinées. */
    private static function meetings(User $user, Carbon $from, Carbon $to): Collection
    {
        return Meeting::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$from, $to])
            ->whereHas('attendees', fn ($query) => $query->where('user_id', $user->id)->whereNotNull('invited_at')->where('response', '!=', 'declined'))
            ->with('club:id,title')
            ->get()
            ->map(fn (Meeting $meeting): array => self::entry(
                'meeting',
                $meeting->title,
                trim(($meeting->club?->title ?? '').($meeting->location ? ' · '.$meeting->location : ''), ' ·'),
                $meeting->starts_at,
                $meeting->endsAt(),
                false,
                route('meetings.mine'),
                null,
                $meeting->status === 'held' ? 'Tenue' : null,
            ));
    }

    /** Restreint une requête (colonnes starts_at / ends_at) aux éléments qui touchent la période. */
    private static function overlapping(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('starts_at', '<=', $to)->where(fn (Builder $inner) => $inner->where('starts_at', '>=', $from)->orWhere('ends_at', '>=', $from));
    }

    /** @return array<string, mixed> */
    private static function entry(string $kind, string $title, string $meta, Carbon $at, ?Carbon $end, bool $allDay, ?string $url, ?string $state, ?string $badge): array
    {
        return [
            'kind' => $kind,
            'title' => $title,
            'meta' => $meta,
            'at' => $at,
            'ends_at' => $end,
            'all_day' => $allDay,
            'url' => $url,
            'state' => $state,
            'badge' => $badge,
            'time' => $allDay ? null : $at->format('H:i'),
            'first_day' => true,
        ];
    }

    /**
     * Répartit les entrées par jour (une entrée sur plusieurs jours apparaît chaque jour, l'heure seulement le premier).
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function placeByDay(Collection $entries, Carbon $from, Carbon $to): array
    {
        $first = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();
        $days = [];

        foreach ($entries as $entry) {
            $start = $entry['at']->copy()->startOfDay();
            $end = $entry['ends_at'];
            $stop = $start->copy();

            if (! $entry['all_day'] && $end !== null && $end->gt($entry['at'])) {
                $stop = ($end->isStartOfDay() ? $end->copy()->subSecond() : $end->copy())->startOfDay();
            }

            for ($day = $start->copy(), $n = 0; $day->lte($stop) && $n < self::MAX_SPAN_DAYS; $day->addDay(), $n++) {
                if ($day->lt($first) || $day->gt($last)) {
                    continue;
                }

                $days[$day->toDateString()][] = $n === 0
                    ? $entry
                    : ['first_day' => false, 'time' => null, 'all_day' => true, 'badge' => $entry['badge']] + $entry;
            }
        }

        return $days;
    }
}
