<?php

namespace App\Support\Academy;

use App\Models\AcademyCourse;
use App\Models\AcademyEnrollment;
use App\Models\AcademyLesson;
use App\Models\AcademySubscription;
use App\Models\User;

/** Qui peut suivre quel cours : cours gratuit, achat, abonnement actif. */
final class AcademyAccess
{
    public static function hasSubscription(User $user): bool
    {
        return AcademySubscription::query()->where('user_id', $user->id)->where('ends_at', '>', now())->exists();
    }

    public static function enrollment(User $user, AcademyCourse $course): ?AcademyEnrollment
    {
        return AcademyEnrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->first();
    }

    public static function canAccess(User $user, AcademyCourse $course): bool
    {
        if (! $course->isPublished()) {
            return false;
        }

        return $course->isFree()
            || self::enrollment($user, $course) !== null
            || ($course->included_in_plans && self::hasSubscription($user));
    }

    /** Une leçon d'aperçu est visible par tout membre ; les autres exigent l'accès au cours. */
    public static function canView(User $user, AcademyLesson $lesson): bool
    {
        $course = $lesson->module->course;

        if (! $course->isPublished()) {
            return false;
        }

        return $lesson->is_preview || self::canAccess($user, $course);
    }

    /** Inscrit le membre au cours (sans effet s'il l'est déjà). */
    public static function enroll(User $user, AcademyCourse $course, string $source): AcademyEnrollment
    {
        return AcademyEnrollment::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['source' => $source, 'enrolled_at' => now()],
        );
    }

    /** Origine de l'accès à enregistrer lors de l'inscription. */
    public static function sourceFor(User $user, AcademyCourse $course): string
    {
        if ($course->isFree()) {
            return 'free';
        }

        return $course->included_in_plans && self::hasSubscription($user) ? 'plan' : 'admin';
    }
}
