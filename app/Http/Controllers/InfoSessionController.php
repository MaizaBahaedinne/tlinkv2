<?php

namespace App\Http\Controllers;

use App\Models\QuizAttempt;
use App\Support\InfoSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Info session (vidéo) puis quiz : réussir le quiz valide le compte.
 */
class InfoSessionController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        InfoSession::closeExpired($user);

        $settings = InfoSession::settings();
        $videoId = InfoSession::videoId($settings['video_url']);

        $last = QuizAttempt::query()->where('user_id', $user->id)->where('status', '!=', 'in_progress')->latest('id')->first();

        return view('info-session.show', [
            'settings' => $settings,
            'videoId' => $videoId,
            'validated' => $user->onboarding_completed_at !== null,
            'validatedAt' => $user->onboarding_completed_at,
            'last' => $last,
            'review' => $last ? $this->review($last) : [],
            'open' => QuizAttempt::query()->where('user_id', $user->id)->where('status', 'in_progress')->exists(),
        ]);
    }

    /**
     * Correction d'une tentative corrigée : pour chaque question, les réponses cochées (justes ou fausses)
     * et les bonnes réponses manquées. Une tentative dont le temps est écoulé n'a pas de réponses à corriger.
     *
     * @return array<int, array{text: string, type: string, ok: bool, answered: bool, options: array<int, array{text: string, state: string}>}>
     */
    private function review(QuizAttempt $attempt): array
    {
        if (! in_array($attempt->status, ['passed', 'failed'], true) || $attempt->answers === null) {
            return [];
        }

        $review = [];

        foreach ($attempt->snapshot['questions'] as $question) {
            $given = $attempt->answers[$question['id']] ?? [];
            $correct = $attempt->snapshot['correct'][$question['id']] ?? [];
            $options = [];

            foreach ($question['options'] as $option) {
                $chosen = in_array($option['id'], $given, true);
                $right = in_array($option['id'], $correct, true);

                $options[] = ['text' => $option['text'], 'state' => match (true) {
                    $chosen && $right => 'right',
                    $chosen => 'wrong',
                    $right => 'missed',
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
                'options' => $options,
            ];
        }

        return $review;
    }

    public function start(Request $request): RedirectResponse
    {
        $attempt = InfoSession::start($request->user());

        return redirect()->route('info-session.quiz', $attempt);
    }

    public function quiz(Request $request, QuizAttempt $attempt): View|RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
        InfoSession::closeExpired($request->user());
        $attempt->refresh();

        if (! $attempt->isOpen()) {
            return redirect()->route('info-session.show');
        }

        return view('info-session.quiz', [
            'attempt' => $attempt,
            'title' => InfoSession::settings()['title'],
            'questions' => $attempt->snapshot['questions'],
            'remaining' => max(0, (int) now()->diffInSeconds($attempt->expires_at, false)),
            'minutes' => (int) round($attempt->started_at->diffInSeconds($attempt->expires_at) / 60),
        ]);
    }

    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'answers' => ['nullable', 'array', 'max:'.InfoSession::MAX_QUESTIONS],
            'answers.*' => ['nullable', 'array', 'max:'.InfoSession::MAX_OPTIONS],
            'answers.*.*' => ['string', 'max:20'],
        ]);

        $result = InfoSession::submit($attempt, $data['answers'] ?? []);

        return redirect()->route('info-session.show')->with('quiz_result', $result->id);
    }
}
