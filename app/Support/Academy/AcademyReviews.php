<?php

namespace App\Support\Academy;

use App\Models\AcademyCourse;
use App\Models\AcademyReview;
use App\Models\User;

/** Avis des membres sur un cours et son formateur. Seuls les membres inscrits au cours peuvent en laisser un. */
final class AcademyReviews
{
    public static function canReview(User $user, AcademyCourse $course): bool
    {
        return AcademyAccess::enrollment($user, $course) !== null;
    }

    /**
     * Moyennes et répartition des notes de la formation, moyenne des notes du formateur (avis masqués exclus).
     *
     * @return array{count: int, course_avg: ?float, instructor_count: int, instructor_avg: ?float, distribution: array<int, int>}
     */
    public static function summary(AcademyCourse $course): array
    {
        $visible = AcademyReview::query()->where('course_id', $course->id)->where('is_hidden', false);

        $count = (clone $visible)->count();
        $instructorCount = (clone $visible)->whereNotNull('instructor_rating')->count();
        $distribution = array_fill_keys([5, 4, 3, 2, 1], 0);

        foreach ((clone $visible)->selectRaw('course_rating, count(*) as total')->groupBy('course_rating')->pluck('total', 'course_rating') as $rating => $total) {
            $distribution[(int) $rating] = (int) $total;
        }

        return [
            'count' => $count,
            'course_avg' => $count > 0 ? round((float) (clone $visible)->avg('course_rating'), 1) : null,
            'instructor_count' => $instructorCount,
            'instructor_avg' => $instructorCount > 0 ? round((float) (clone $visible)->avg('instructor_rating'), 1) : null,
            'distribution' => $distribution,
        ];
    }

    /** « Prénom N. » : les avis publics n'exposent pas le nom complet. */
    public static function displayName(User $user): string
    {
        $first = trim((string) ($user->first_name ?: strtok((string) $user->name, ' ')));
        $last = trim((string) $user->last_name);

        if ($last === '' && str_contains((string) $user->name, ' ')) {
            $last = trim((string) substr((string) strstr((string) $user->name, ' '), 1));
        }

        return $last === '' ? ($first ?: 'Membre') : $first.' '.mb_strtoupper(mb_substr($last, 0, 1)).'.';
    }
}
