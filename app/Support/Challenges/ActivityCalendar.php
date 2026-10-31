<?php

namespace App\Support\Challenges;

use App\Models\ActivityRegistration;
use App\Models\ClubActivity;
use App\Models\User;

/** Calendrier du fil d'actualité : prochaines activités de clubs accessibles au membre, avec l'état de son inscription. */
final class ActivityCalendar
{
    /** Horizon du calendrier, en jours. */
    public const HORIZON_DAYS = 90;

    public const LIMIT = 40;

    /**
     * États : open (inscription possible), full (liste d'attente), closed (inscriptions closes),
     * registered et waitlisted (déjà inscrit).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for(?User $user): array
    {
        if ($user === null || ! Challenges::enabled()) {
            return [];
        }

        $scopes = ActivityAccess::scopesOf($user);

        $activities = ClubActivity::query()
            ->where('status', 'published')
            ->whereHas('challenge', fn ($query) => $query->where('status', 'published'))
            ->where('starts_at', '<=', now()->addDays(self::HORIZON_DAYS))
            ->where(fn ($query) => $query
                ->where(fn ($ended) => $ended->whereNotNull('ends_at')->where('ends_at', '>=', now()))
                ->orWhere(fn ($open) => $open->whereNull('ends_at')->where('starts_at', '>=', now()->subHours(4))))
            ->with(['challenge', 'club:id,title', 'organizers'])
            ->orderBy('starts_at')
            ->limit(150)
            ->get();

        if ($activities->isEmpty()) {
            return [];
        }

        $ids = $activities->pluck('id');
        $mine = ActivityRegistration::query()->where('user_id', $user->id)->whereIn('activity_id', $ids)->get()->keyBy('activity_id');
        $counts = ActivityRegistration::query()->where('status', 'registered')->whereIn('activity_id', $ids)
            ->selectRaw('activity_id, COUNT(*) as total')->groupBy('activity_id')->pluck('total', 'activity_id');

        $entries = [];

        foreach ($activities as $activity) {
            $status = $mine->get($activity->id)?->status;
            $joined = in_array($status, ['registered', 'waitlisted'], true);

            // Les activités hors périmètre ou fermées au profil du membre n'encombrent pas son calendrier.
            if (! $joined && (! $activity->challenge->reachesScopes($scopes) || ActivityAccess::ineligibleReason($user, $activity) !== null)) {
                continue;
            }

            $registered = (int) ($counts[$activity->id] ?? 0);
            $full = $activity->capacity !== null && $registered >= $activity->capacity;

            $entries[] = [
                'id' => $activity->id,
                'title' => $activity->title,
                'club' => $activity->club?->title,
                'type' => $activity->typeLabel(),
                'day' => $activity->starts_at->format('Y-m-d'),
                'time' => $activity->starts_at->format('H:i'),
                'location' => $activity->location,
                'state' => $joined ? $status : match (true) {
                    ! $activity->registrationOpen() => 'closed',
                    $full => 'full',
                    default => 'open',
                },
                'needs_form' => $activity->fields() !== [],
                'registered' => $registered,
                'capacity' => $activity->capacity,
                'url' => route('activities.show', $activity),
                'register_url' => route('activities.register', $activity),
                'scan_url' => $status === 'registered' && $activity->checkinOpen() ? route('activities.scan') : null,
            ];

            if (count($entries) >= self::LIMIT) {
                break;
            }
        }

        return $entries;
    }
}
