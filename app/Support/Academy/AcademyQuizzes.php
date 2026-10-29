<?php

namespace App\Support\Academy;

use App\Models\AcademyQuiz;
use App\Models\AcademyQuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Quiz Academy : tirage aléatoire des questions et des réponses, correction côté serveur, niveau évalué.
 * Le tirage est figé dans la tentative : les bonnes réponses ne quittent jamais le serveur.
 */
final class AcademyQuizzes
{
    public const MAX_QUESTIONS = 100;

    public const MAX_OPTIONS = 8;

    /**
     * Tentative en cours du membre pour ce quiz (reprise telle quelle), sinon un nouveau tirage.
     * Renvoie null si le quiz n'a encore aucune question.
     */
    public static function start(User $user, AcademyQuiz $quiz): ?AcademyQuizAttempt
    {
        $open = AcademyQuizAttempt::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->where('status', 'in_progress')->latest('id')->first();

        if ($open !== null) {
            return $open;
        }

        $questions = $quiz->questions()->with('options')->get();

        if ($questions->isEmpty()) {
            return null;
        }

        $drawn = $questions->shuffle()->take($quiz->drawSize($questions->count()))->values();
        $shown = [];
        $correct = [];

        foreach ($drawn as $question) {
            $shown[] = [
                'id' => (string) $question->id,
                'text' => $question->text,
                'type' => $question->type,
                'options' => $question->options->shuffle()->map(fn ($option): array => ['id' => (string) $option->id, 'text' => $option->text])->values()->all(),
            ];
            $correct[(string) $question->id] = $question->options->where('is_correct', true)->map(fn ($option): string => (string) $option->id)->values()->all();
        }

        return AcademyQuizAttempt::query()->create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'status' => 'in_progress',
            'snapshot' => ['questions' => $shown, 'correct' => $correct],
            'started_at' => now(),
        ]);
    }

    /**
     * Corrige la tentative : une question est juste quand l'ensemble des réponses cochées est exactement l'ensemble des bonnes.
     * Réussir le quiz peut terminer le module ou le cours (badges, certificat).
     *
     * @param  array<string, mixed>  $answers  [id de question => [ids de réponses]]
     */
    public static function submit(AcademyQuizAttempt $attempt, array $answers): AcademyQuizAttempt
    {
        return DB::transaction(function () use ($attempt, $answers): AcademyQuizAttempt {
            $attempt = AcademyQuizAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! $attempt->isOpen()) {
                return $attempt;
            }

            $quiz = AcademyQuiz::query()->with('course')->findOrFail($attempt->quiz_id);
            $questions = $attempt->snapshot['questions'];
            $clean = [];
            $right = 0;

            foreach ($questions as $question) {
                $allowed = array_column($question['options'], 'id');
                $given = array_values(array_unique(array_filter(
                    array_map('strval', (array) ($answers[$question['id']] ?? [])),
                    fn (string $id): bool => in_array($id, $allowed, true),
                )));
                $expected = $attempt->snapshot['correct'][$question['id']] ?? [];

                $clean[$question['id']] = $given;
                sort($given);
                sort($expected);
                $right += ($given !== [] && $given === $expected) ? 1 : 0;
            }

            $total = count($questions);
            $score = $total === 0 ? 0 : (int) floor($right / $total * 100);
            $passed = $score >= $quiz->pass_percent;

            $attempt->update([
                'status' => $passed ? 'passed' : 'failed',
                'score_percent' => $score,
                'answers' => $clean,
                'submitted_at' => now(),
            ]);

            if ($passed && $attempt->user !== null) {
                AcademyLearning::evaluate($attempt->user, $quiz->course);
            }

            return $attempt;
        });
    }

    public static function hasPassed(User $user, AcademyQuiz $quiz): bool
    {
        return AcademyQuizAttempt::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->where('status', 'passed')->exists();
    }

    public static function bestScore(User $user, AcademyQuiz $quiz): ?int
    {
        $best = AcademyQuizAttempt::query()->where('user_id', $user->id)->where('quiz_id', $quiz->id)->whereNotNull('score_percent')->max('score_percent');

        return $best === null ? null : (int) $best;
    }

    /** Niveau évalué d'après le score. */
    public static function level(int $percent): string
    {
        return match (true) {
            $percent < 50 => 'Débutant',
            $percent < 80 => 'Intermédiaire',
            default => 'Avancé',
        };
    }

    /**
     * Correction d'une tentative terminée : pour chaque question, réponses cochées (justes ou fausses) et bonnes réponses manquées.
     *
     * @return array<int, array{text: string, type: string, ok: bool, answered: bool, explanation: ?string, options: array<int, array{text: string, state: string}>}>
     */
    public static function review(AcademyQuizAttempt $attempt): array
    {
        if ($attempt->isOpen() || $attempt->answers === null) {
            return [];
        }

        $explanations = \App\Models\AcademyQuizQuestion::query()
            ->whereIn('id', array_map('intval', array_column($attempt->snapshot['questions'], 'id')))
            ->pluck('explanation', 'id');

        $review = [];

        foreach ($attempt->snapshot['questions'] as $question) {
            $given = $attempt->answers[$question['id']] ?? [];
            $correct = $attempt->snapshot['correct'][$question['id']] ?? [];
            $options = [];

            foreach ($question['options'] as $option) {
                $chosen = in_array($option['id'], $given, true);
                $isRight = in_array($option['id'], $correct, true);

                $options[] = ['text' => $option['text'], 'state' => match (true) {
                    $chosen && $isRight => 'right',
                    $chosen => 'wrong',
                    $isRight => 'missed',
                    default => 'neutral',
                }];
            }

            sort($given);
            sort($correct);

            $review[] = [
                'text' => $question['text'],
                'type' => $question['type'],
                'ok' => $given !== [] && $given === $correct,
                'answered' => $given !== [],
                'explanation' => $explanations[(int) $question['id']] ?? null,
                'options' => $options,
            ];
        }

        return $review;
    }
}
