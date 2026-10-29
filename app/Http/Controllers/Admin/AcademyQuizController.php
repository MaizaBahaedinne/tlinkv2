<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademyCourse;
use App\Models\AcademyQuiz;
use App\Models\AcademyQuizQuestion;
use App\Support\Academy\AcademyQuizzes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Quiz d'un module ou quiz final d'un cours : réglages (seuil, tirage) et banque de questions.
 */
class AcademyQuizController extends AdminController
{
    public function store(Request $request, AcademyCourse $course): RedirectResponse
    {
        $data = $request->validate([
            'module_id' => ['nullable', 'integer', Rule::exists('academy_modules', 'id')->where('course_id', $course->id)],
        ]);

        $moduleId = $data['module_id'] ?? null;
        $existing = AcademyQuiz::query()->where('course_id', $course->id)->where('module_id', $moduleId)->first();

        if ($existing !== null) {
            return redirect()->route('admin.academy.courses.quiz.edit', $existing);
        }

        $module = $moduleId !== null ? $course->modules()->find($moduleId) : null;
        $quiz = AcademyQuiz::query()->create([
            'course_id' => $course->id,
            'module_id' => $moduleId,
            'title' => $module !== null ? 'Quiz : '.$module->title : 'Quiz final : '.$course->title,
            'pass_percent' => 70,
            'is_required' => true,
        ]);

        $this->audit($request, 'admin.academy.quiz.saved', $quiz, ['title' => $quiz->title]);

        return redirect()->route('admin.academy.courses.quiz.edit', $quiz)->with('status', 'Quiz créé : ajoutez ses questions.');
    }

    public function edit(AcademyQuiz $quiz): View
    {
        $quiz->load(['course', 'module', 'questions.options']);

        return view('admin.academy.quizzes.edit', ['quiz' => $quiz]);
    }

    public function update(Request $request, AcademyQuiz $quiz): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'intro' => ['nullable', 'string', 'max:3000'],
            'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'questions_per_attempt' => ['nullable', 'integer', 'min:1', 'max:'.AcademyQuizzes::MAX_QUESTIONS],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $data['is_required'] = $request->boolean('is_required');
        $quiz->update($data);
        $this->audit($request, 'admin.academy.quiz.saved', $quiz, ['title' => $quiz->title, 'pass_percent' => $quiz->pass_percent]);

        return back()->with('status', 'Quiz enregistré.');
    }

    public function destroy(Request $request, AcademyQuiz $quiz): RedirectResponse
    {
        $courseId = $quiz->course_id;
        $this->audit($request, 'admin.academy.quiz.deleted', $quiz, ['title' => $quiz->title]);
        $quiz->delete();

        return redirect()->route('admin.academy.courses.edit', $courseId)->with('status', 'Quiz supprimé.');
    }

    public function storeQuestion(Request $request, AcademyQuiz $quiz): RedirectResponse
    {
        if ($quiz->questions()->count() >= AcademyQuizzes::MAX_QUESTIONS) {
            return back()->withErrors(['question' => 'Ce quiz atteint le nombre maximal de questions ('.AcademyQuizzes::MAX_QUESTIONS.').']);
        }

        [$data, $options] = $this->validatedQuestion($request);

        DB::transaction(function () use ($quiz, $data, $options): void {
            $question = $quiz->questions()->create($data + ['sort_order' => ((int) $quiz->questions()->max('sort_order')) + 1]);
            $question->options()->createMany($options);
        });

        $this->audit($request, 'admin.academy.question.saved', $quiz, ['quiz' => $quiz->title]);

        return back()->with('status', 'Question ajoutée.');
    }

    public function updateQuestion(Request $request, AcademyQuizQuestion $question): RedirectResponse
    {
        [$data, $options] = $this->validatedQuestion($request);

        DB::transaction(function () use ($question, $data, $options): void {
            $question->update($data);
            $question->options()->delete();
            $question->options()->createMany($options);
        });

        $this->audit($request, 'admin.academy.question.saved', $question->quiz, ['quiz' => $question->quiz->title]);

        return back()->with('status', 'Question enregistrée.');
    }

    public function destroyQuestion(Request $request, AcademyQuizQuestion $question): RedirectResponse
    {
        $this->audit($request, 'admin.academy.question.deleted', $question->quiz, ['quiz' => $question->quiz->title]);
        $question->delete();

        return back()->with('status', 'Question supprimée.');
    }

    /**
     * Les réponses se saisissent en lignes ; les lignes vides sont ignorées.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function validatedQuestion(Request $request): array
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::in(array_keys(AcademyQuizQuestion::TYPES))],
            'explanation' => ['nullable', 'string', 'max:1000'],
            'options' => ['required', 'array', 'max:'.AcademyQuizzes::MAX_OPTIONS],
            'options.*.text' => ['nullable', 'string', 'max:255'],
            'options.*.correct' => ['nullable', 'boolean'],
        ]);

        $options = [];

        foreach ($data['options'] as $row) {
            $text = trim((string) ($row['text'] ?? ''));

            if ($text !== '') {
                $options[] = ['text' => $text, 'is_correct' => ! empty($row['correct']), 'sort_order' => count($options) + 1];
            }
        }

        $correct = collect($options)->where('is_correct', true)->count();

        if (count($options) < 2) {
            throw ValidationException::withMessages(['options' => 'Indiquez au moins deux réponses.']);
        }

        if ($correct === 0) {
            throw ValidationException::withMessages(['options' => 'Cochez au moins une bonne réponse.']);
        }

        if ($data['type'] === 'single' && $correct !== 1) {
            throw ValidationException::withMessages(['options' => 'Une question à réponse unique n’a qu’une seule bonne réponse : cochez-en une, ou choisissez « Plusieurs bonnes réponses ».']);
        }

        unset($data['options']);
        $data['explanation'] = filled($data['explanation'] ?? null) ? $data['explanation'] : null;

        return [$data, $options];
    }
}
