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

        return view('info-session.show', [
            'settings' => $settings,
            'videoId' => $videoId,
            'validated' => $user->onboarding_completed_at !== null,
            'validatedAt' => $user->onboarding_completed_at,
            'last' => QuizAttempt::query()->where('user_id', $user->id)->where('status', '!=', 'in_progress')->latest('id')->first(),
            'open' => QuizAttempt::query()->where('user_id', $user->id)->where('status', 'in_progress')->exists(),
        ]);
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
