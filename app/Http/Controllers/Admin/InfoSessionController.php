<?php

namespace App\Http\Controllers\Admin;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\InfoSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Info session et quiz de validation des comptes : vidéo, questions, durée et seuil de réussite.
 */
class InfoSessionController extends AdminController
{
    public function edit(Request $request): View
    {
        $settings = InfoSession::settings();
        $old = $request->old('questions');

        return view('admin.info-session.edit', [
            'settings' => $settings,
            'questions' => is_array($old) ? array_values($old) : $settings['questions'],
            'videoId' => InfoSession::videoId($settings['video_url']),
            'maxOptions' => InfoSession::MAX_OPTIONS,
            'stats' => [
                'members' => User::query()->count(),
                'validated' => User::query()->whereNotNull('onboarding_completed_at')->count(),
                'attempts' => QuizAttempt::query()->where('status', '!=', 'in_progress')->count(),
                'passed' => QuizAttempt::query()->where('status', 'passed')->count(),
            ],
            'attempts' => QuizAttempt::query()->with('user:id,name,email')->where('status', '!=', 'in_progress')->latest('id')->limit(10)->get(),
            'statuses' => QuizAttempt::STATUSES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'video_url' => ['required', 'url', 'max:255'],
            'title' => ['required', 'string', 'max:150'],
            'intro' => ['nullable', 'string', 'max:3000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'questions' => ['nullable', 'array', 'max:'.InfoSession::MAX_QUESTIONS],
            'questions.*' => ['array'],
            'questions.*.id' => ['nullable', 'string', 'max:20'],
            'questions.*.text' => ['nullable', 'string', 'max:500'],
            'questions.*.type' => ['nullable', 'in:single,multiple'],
            'questions.*.options' => ['nullable', 'array', 'max:'.InfoSession::MAX_OPTIONS],
            'questions.*.options.*.text' => ['nullable', 'string', 'max:300'],
            'questions.*.options.*.correct' => ['nullable'],
        ]);

        if (InfoSession::videoId($data['video_url']) === null) {
            throw ValidationException::withMessages(['video_url' => 'Collez un lien YouTube (youtube.com/watch?v=… ou youtu.be/…).']);
        }

        $questions = InfoSession::normalizeQuestions($data['questions'] ?? []);

        InfoSession::save([
            'video_url' => $data['video_url'],
            'title' => trim($data['title']),
            'intro' => trim((string) ($data['intro'] ?? '')),
            'duration_minutes' => (int) $data['duration_minutes'],
            'pass_percent' => (int) $data['pass_percent'],
            'questions' => $questions,
        ]);

        AuditLogger::record($request->user()->id, 'admin.info_session.updated', null, null, ['questions' => count($questions), 'pass_percent' => (int) $data['pass_percent']], $request->ip());

        return redirect()->route('admin.info-session.edit')->with('status', 'Info session enregistrée : '.count($questions).' question(s).');
    }
}
