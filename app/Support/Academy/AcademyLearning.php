<?php

namespace App\Support\Academy;

use App\Models\AcademyCourse;
use App\Models\AcademyEnrollment;
use App\Models\AcademyLesson;
use App\Models\AcademyLessonCompletion;
use App\Models\AcademyQuiz;
use App\Models\AcademyQuizAttempt;
use App\Models\AppNotification;
use App\Models\Badge;
use App\Models\User;
use App\Support\Gamification\GamificationEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Progression des membres dans les cours : leçons terminées, points, badges de module et de cours, certificat.
 * Les points sont calculés depuis les données (comme le reste de la gamification) : ils nourrissent les missions
 * via les activités `academy.*` du catalogue (ActivityCatalog).
 */
final class AcademyLearning
{
    /**
     * Marque une leçon terminée ; renvoie false si le membre n'a pas accès au cours.
     */
    public static function completeLesson(User $user, AcademyLesson $lesson): bool
    {
        $lesson->loadMissing('module.course');
        $course = $lesson->module->course;

        if (! AcademyAccess::canAccess($user, $course)) {
            return false;
        }

        AcademyAccess::enroll($user, $course, AcademyAccess::sourceFor($user, $course));

        AcademyLessonCompletion::query()->firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['completed_at' => now()],
        );

        self::evaluate($user, $course);

        return true;
    }

    /**
     * Décerne les badges de module et de cours, et clôt le cours (certificat) quand tout est terminé.
     */
    public static function evaluate(User $user, AcademyCourse $course): void
    {
        $course->load('modules.lessons');
        $done = AcademyLessonCompletion::query()->where('user_id', $user->id)
            ->whereIn('lesson_id', $course->modules->flatMap->lessons->pluck('id'))->pluck('lesson_id')->all();

        $allDone = true;
        $hasContent = false;

        // Quiz obligatoires (et non vides) du cours : chacun doit avoir été réussi au moins une fois.
        $quizzes = AcademyQuiz::query()->where('course_id', $course->id)->where('is_required', true)->whereHas('questions')->get();
        $passed = AcademyQuizAttempt::query()->where('user_id', $user->id)->where('status', 'passed')
            ->whereIn('quiz_id', $quizzes->pluck('id'))->pluck('quiz_id')->all();
        $quizzesOf = fn (?int $moduleId) => $quizzes->filter(fn (AcademyQuiz $quiz): bool => $quiz->module_id === $moduleId);
        $quizzesPassed = fn (?int $moduleId): bool => $quizzesOf($moduleId)->every(fn (AcademyQuiz $quiz): bool => in_array($quiz->id, $passed, true));

        foreach ($course->modules as $module) {
            $ids = $module->lessons->pluck('id');
            $hasQuiz = $quizzesOf($module->id)->isNotEmpty();
            $moduleDone = ($ids->isNotEmpty() || $hasQuiz)
                && $ids->every(fn (int $id): bool => in_array($id, $done, true))
                && $quizzesPassed($module->id);
            $hasContent = $hasContent || $ids->isNotEmpty() || $hasQuiz;
            $allDone = $allDone && ($moduleDone || ($ids->isEmpty() && ! $hasQuiz));

            if ($moduleDone && $module->badge_id !== null && ($badge = Badge::query()->find($module->badge_id)) !== null) {
                GamificationEngine::award($user->id, $badge);
            }
        }

        $hasContent = $hasContent || $quizzesOf(null)->isNotEmpty();

        if (! $hasContent || ! $allDone || ! $quizzesPassed(null)) {
            return;
        }

        $enrollment = AcademyAccess::enrollment($user, $course);

        if ($enrollment === null || $enrollment->completed_at !== null) {
            return;
        }

        $enrollment->update(['completed_at' => now(), 'certificate_code' => self::certificateCode()]);

        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'academy.course_completed',
            'title' => 'Cours terminé : '.$course->title,
            'body' => 'Félicitations ! Votre certificat '.AcademySettings::NAME.' est disponible.',
            'data' => ['course_id' => $course->id, 'certificate' => $enrollment->certificate_code],
        ]);

        if ($course->badge_id !== null && ($badge = Badge::query()->find($course->badge_id)) !== null) {
            GamificationEngine::award($user->id, $badge);
        }
    }

    /**
     * @return array{done: int, total: int, percent: int, completed_ids: array<int, int>}
     */
    public static function progress(User $user, AcademyCourse $course): array
    {
        $lessonIds = $course->lessons()->pluck('academy_lessons.id')->all();
        $completed = AcademyLessonCompletion::query()->where('user_id', $user->id)->whereIn('lesson_id', $lessonIds)->pluck('lesson_id')->all();
        $total = count($lessonIds);

        return [
            'done' => count($completed),
            'total' => $total,
            'percent' => $total === 0 ? 0 : (int) floor(count($completed) / $total * 100),
            'completed_ids' => $completed,
        ];
    }

    /** Points Academy d'un membre : leçons terminées + bonus de fin de cours. */
    public static function points(int $userId): int
    {
        $lessons = (int) AcademyLessonCompletion::query()->where('user_id', $userId)
            ->join('academy_lessons', 'academy_lessons.id', '=', 'academy_lesson_completions.lesson_id')
            ->sum('academy_lessons.points');

        $courses = (int) AcademyEnrollment::query()->where('user_id', $userId)->whereNotNull('completed_at')
            ->join('academy_courses', 'academy_courses.id', '=', 'academy_enrollments.course_id')
            ->sum('academy_courses.completion_points');

        return $lessons + $courses;
    }

    private static function certificateCode(): string
    {
        do {
            $code = 'TA-'.strtoupper(Str::random(10));
        } while (AcademyEnrollment::query()->where('certificate_code', $code)->exists());

        return $code;
    }
}
