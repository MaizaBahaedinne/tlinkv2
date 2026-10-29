<?php

namespace App\Http\Controllers;

use App\Models\AcademyQuiz;
use App\Models\AcademyQuizAttempt;
use App\Support\Academy\AcademyAccess;
use App\Support\Academy\AcademyQuizzes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quiz Academy côté membre : présentation, tirage aléatoire, envoi des réponses, correction et niveau évalué.
 */
class AcademyQuizController extends Controller
{
    public function show(Request $request, AcademyQuiz $quiz): View|RedirectResponse
    {
        $quiz->load(['course', 'module']);
        abort_unless($quiz->course->isPublished(), 404);
        $user = $request->user();

        if (! AcademyAccess::canAccess($user, $quiz->course)) {
            return redirect()->route('academy.courses.show', $quiz->course->slug)->with('status', 'Débloquez ce cours pour passer son quiz.');
        }

        $attempts = AcademyQuizAttempt::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->where('status', '!=', 'in_progress')
            ->latest('id')->limit(5)->get();

        return view('academy.quiz', [
            'quiz' => $quiz,
            'questionCount' => $quiz->questions()->count(),
            'attempts' => $attempts,
            'passed' => AcademyQuizzes::hasPassed($user, $quiz),
            'best' => AcademyQuizzes::bestScore($user, $quiz),
            'hasOpen' => AcademyQuizAttempt::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->where('status', 'in_progress')->exists(),
        ]);
    }

    public function start(Request $request, AcademyQuiz $quiz): RedirectResponse
    {
        $quiz->load('course');
        abort_unless($quiz->course->isPublished(), 404);
        $user = $request->user();

        if (! AcademyAccess::canAccess($user, $quiz->course)) {
            return redirect()->route('academy.courses.show', $quiz->course->slug)->with('status', 'Débloquez ce cours pour passer son quiz.');
        }

        AcademyAccess::enroll($user, $quiz->course, AcademyAccess::sourceFor($user, $quiz->course));

        $attempt = AcademyQuizzes::start($user, $quiz);

        return $attempt === null
            ? redirect()->route('academy.quizzes.show', $quiz)->with('status', 'Ce quiz n’a pas encore de questions.')
            : redirect()->route('academy.attempts.show', $attempt);
    }

    /** Tentative en cours : le formulaire. Tentative corrigée : le résultat et la correction. */
    public function attempt(Request $request, AcademyQuizAttempt $attempt): View
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
        $attempt->load('quiz.course');

        if ($attempt->isOpen()) {
            return view('academy.quiz-attempt', ['attempt' => $attempt, 'quiz' => $attempt->quiz, 'questions' => $attempt->snapshot['questions']]);
        }

        $course = $attempt->quiz->course;
        $enrollment = AcademyAccess::enrollment($request->user(), $course);

        return view('academy.quiz-result', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'course' => $course,
            'passed' => $attempt->status === 'passed',
            'level' => AcademyQuizzes::level((int) $attempt->score_percent),
            'review' => AcademyQuizzes::review($attempt),
            'certificateCode' => $enrollment?->completed_at !== null ? $enrollment->certificate_code : null,
        ]);
    }

    public function submit(Request $request, AcademyQuizAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'answers' => ['nullable', 'array', 'max:'.AcademyQuizzes::MAX_QUESTIONS],
            'answers.*' => ['nullable', 'array', 'max:'.AcademyQuizzes::MAX_OPTIONS],
            'answers.*.*' => ['string', 'max:20'],
        ]);

        AcademyQuizzes::submit($attempt, $data['answers'] ?? []);

        return redirect()->route('academy.attempts.show', $attempt);
    }
}
