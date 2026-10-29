<?php

namespace Tests\Feature;

use App\Models\AcademyCourse;
use App\Models\AcademyEnrollment;
use App\Models\AcademyQuiz;
use App\Models\AcademyQuizAttempt;
use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\Academy\AcademyLearning;
use App\Support\Academy\AcademyQuizzes;
use App\Support\Gamification\ActivityCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AcademyQuizTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function course(?float $price = null): AcademyCourse
    {
        $course = AcademyCourse::query()->create([
            'title' => 'Leadership', 'slug' => 'leadership', 'level' => 'beginner', 'price' => $price,
            'included_in_plans' => true, 'completion_points' => 50, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Bases', 'sort_order' => 1]);
        $module->lessons()->create(['title' => 'Leçon 1', 'kind' => 'text', 'content' => 'Contenu', 'points' => 10, 'sort_order' => 1]);

        return $course;
    }

    /** Quiz de 4 questions à réponse unique : la bonne réponse est toujours « Bonne ». */
    private function quiz(AcademyCourse $course, bool $module = true, array $attributes = []): AcademyQuiz
    {
        $quiz = AcademyQuiz::query()->create($attributes + [
            'course_id' => $course->id,
            'module_id' => $module ? $course->modules()->first()->id : null,
            'title' => 'Quiz test', 'pass_percent' => 70, 'is_required' => true,
        ]);

        foreach (range(1, 4) as $n) {
            $question = $quiz->questions()->create(['text' => "Question $n", 'type' => 'single', 'sort_order' => $n, 'explanation' => "Explication $n"]);
            $question->options()->createMany([
                ['text' => 'Bonne', 'is_correct' => true, 'sort_order' => 1],
                ['text' => 'Fausse A', 'is_correct' => false, 'sort_order' => 2],
                ['text' => 'Fausse B', 'is_correct' => false, 'sort_order' => 3],
            ]);
        }

        return $quiz;
    }

    /** Réponses d'une tentative : $good bonnes réponses, le reste faux. */
    private function answers(AcademyQuizAttempt $attempt, int $good): array
    {
        $answers = [];
        foreach ($attempt->snapshot['questions'] as $index => $question) {
            $ids = $attempt->snapshot['correct'][$question['id']];
            if ($index < $good) {
                $answers[$question['id']] = $ids;
            } else {
                $wrong = collect($question['options'])->pluck('id')->diff($ids)->first();
                $answers[$question['id']] = [$wrong];
            }
        }

        return $answers;
    }

    public function test_admin_builds_a_quiz_and_validates_questions(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $course = $this->course();
        $module = $course->modules()->first();

        $this->actingAs($admin)->post(route('admin.academy.courses.quiz.store', $course), ['module_id' => $module->id])->assertRedirect();
        $quiz = AcademyQuiz::query()->firstOrFail();
        $this->assertSame($module->id, $quiz->module_id);

        // Un second appel réutilise le quiz existant.
        $this->actingAs($admin)->post(route('admin.academy.courses.quiz.store', $course), ['module_id' => $module->id]);
        $this->assertSame(1, AcademyQuiz::query()->count());

        $this->actingAs($admin)->get(route('admin.academy.courses.quiz.edit', $quiz))->assertOk()->assertSee('Quiz');

        $url = route('admin.academy.courses.quiz.questions.store', $quiz);
        $options = [['text' => 'A', 'correct' => '1'], ['text' => 'B', 'correct' => '0'], ['text' => '', 'correct' => '0']];

        $this->actingAs($admin)->post($url, ['text' => 'Q ?', 'type' => 'single', 'options' => $options])->assertRedirect();
        $this->assertSame(1, $quiz->questions()->count());
        $this->assertSame(2, $quiz->questions()->first()->options()->count());

        $this->actingAs($admin)->post($url, ['text' => 'Q2', 'type' => 'single', 'options' => [['text' => 'A', 'correct' => '1']]])->assertSessionHasErrors('options');
        $this->actingAs($admin)->post($url, ['text' => 'Q2', 'type' => 'single', 'options' => [['text' => 'A', 'correct' => '0'], ['text' => 'B', 'correct' => '0']]])->assertSessionHasErrors('options');
        $this->actingAs($admin)->post($url, ['text' => 'Q2', 'type' => 'single', 'options' => [['text' => 'A', 'correct' => '1'], ['text' => 'B', 'correct' => '1']]])->assertSessionHasErrors('options');
        $this->actingAs($admin)->post($url, ['text' => 'Q2', 'type' => 'multiple', 'options' => [['text' => 'A', 'correct' => '1'], ['text' => 'B', 'correct' => '1']]])->assertSessionHasNoErrors();
        $this->assertSame(2, $quiz->questions()->count());

        $this->actingAs($admin)->put(route('admin.academy.courses.quiz.update', $quiz), ['title' => 'Mon quiz', 'pass_percent' => 80, 'questions_per_attempt' => 1, 'is_required' => '0'])->assertRedirect();
        $quiz->refresh();
        $this->assertSame(80, $quiz->pass_percent);
        $this->assertFalse($quiz->is_required);

        $this->actingAs($admin)->get(route('admin.academy.courses.edit', $course))->assertOk()->assertSee('Gérer le quiz');

        $this->actingAs($admin)->delete(route('admin.academy.courses.quiz.destroy', $quiz))->assertRedirect();
        $this->assertSame(0, AcademyQuiz::query()->count());
    }

    public function test_regular_members_cannot_manage_quizzes(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $quiz = $this->quiz($course);

        $this->actingAs($user)->get(route('admin.academy.courses.quiz.edit', $quiz))->assertForbidden();
        $this->actingAs($user)->post(route('admin.academy.courses.quiz.store', $course))->assertForbidden();
    }

    public function test_member_passes_a_quiz_and_sees_correction_and_level(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $quiz = $this->quiz($course);

        $this->actingAs($user)->get(route('academy.quizzes.show', $quiz))->assertOk()->assertSee('Commencer le quiz');
        $this->actingAs($user)->post(route('academy.quizzes.start', $quiz))->assertRedirect();

        $attempt = AcademyQuizAttempt::query()->firstOrFail();
        $this->assertTrue($attempt->isOpen());
        $this->assertCount(4, $attempt->snapshot['questions']);
        $this->assertNotNull(AcademyEnrollment::query()->where('user_id', $user->id)->first());

        // Reprise : pas de nouveau tirage.
        $this->actingAs($user)->post(route('academy.quizzes.start', $quiz));
        $this->assertSame(1, AcademyQuizAttempt::query()->count());

        $this->actingAs($user)->get(route('academy.attempts.show', $attempt))->assertOk()->assertSee('Envoyer mes réponses')->assertDontSee('Explication 1');

        $this->actingAs($user)->post(route('academy.attempts.submit', $attempt), ['answers' => $this->answers($attempt, 4)])->assertRedirect(route('academy.attempts.show', $attempt));
        $attempt->refresh();
        $this->assertSame('passed', $attempt->status);
        $this->assertSame(100, $attempt->score_percent);

        $this->actingAs($user)->get(route('academy.attempts.show', $attempt))->assertOk()->assertSee('100 %')->assertSee('Avancé')->assertSee('Quiz réussi')->assertSee('Explication');
        $this->assertTrue(AcademyQuizzes::hasPassed($user, $quiz));

        // Renvoyer des réponses ne modifie pas une tentative terminée.
        $this->actingAs($user)->post(route('academy.attempts.submit', $attempt), ['answers' => $this->answers($attempt, 0)]);
        $this->assertSame(100, $attempt->fresh()->score_percent);
    }

    public function test_failed_attempt_can_be_retried_and_levels_are_labelled(): void
    {
        $user = User::factory()->create();
        $quiz = $this->quiz($this->course());

        $attempt = AcademyQuizzes::start($user, $quiz);
        AcademyQuizzes::submit($attempt, $this->answers($attempt, 2));
        $attempt->refresh();

        $this->assertSame('failed', $attempt->status);
        $this->assertSame(50, $attempt->score_percent);
        $this->actingAs($user)->get(route('academy.attempts.show', $attempt))->assertSee('Quiz non réussi')->assertSee('Intermédiaire')->assertSee('Réessayer');

        $second = AcademyQuizzes::start($user, $quiz);
        $this->assertNotSame($attempt->id, $second->id);
        AcademyQuizzes::submit($second, $this->answers($second, 3));
        $this->assertSame(75, AcademyQuizzes::bestScore($user, $quiz));

        $this->assertSame('Débutant', AcademyQuizzes::level(49));
        $this->assertSame('Intermédiaire', AcademyQuizzes::level(79));
        $this->assertSame('Avancé', AcademyQuizzes::level(80));
    }

    public function test_questions_are_drawn_randomly_and_limited(): void
    {
        $user = User::factory()->create();
        $quiz = $this->quiz($this->course(), true, ['questions_per_attempt' => 2]);

        $attempt = AcademyQuizzes::start($user, $quiz);
        $this->assertCount(2, $attempt->snapshot['questions']);
        $this->assertSame(2, $quiz->drawSize(4));

        $orders = [];
        $optionOrders = [];
        $quiz->update(['questions_per_attempt' => null]);
        foreach (range(1, 25) as $i) {
            $attempt = AcademyQuizzes::start(User::factory()->create(), $quiz);
            $orders[] = collect($attempt->snapshot['questions'])->pluck('id')->implode(',');
            $optionOrders[] = collect($attempt->snapshot['questions'][0]['options'])->pluck('text')->implode(',');
        }

        $this->assertGreaterThan(1, count(array_unique($orders)));
        $this->assertGreaterThan(1, count(array_unique($optionOrders)));
    }

    public function test_required_quiz_gates_module_badge_and_certificate(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $module = $course->modules()->first();
        $badge = Badge::query()->create(['slug' => 'bases', 'name' => 'Bases', 'tier' => 'bronze']);
        $module->update(['badge_id' => $badge->id]);
        $quiz = $this->quiz($course);

        AcademyLearning::completeLesson($user, $module->lessons()->first());
        $this->assertFalse(UserBadge::query()->where('badge_id', $badge->id)->exists());
        $this->assertNull(AcademyEnrollment::query()->where('user_id', $user->id)->first()->completed_at);

        $attempt = AcademyQuizzes::start($user, $quiz);
        AcademyQuizzes::submit($attempt, $this->answers($attempt, 4));

        $this->assertTrue(UserBadge::query()->where('user_id', $user->id)->where('badge_id', $badge->id)->exists());
        $enrollment = AcademyEnrollment::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($enrollment->completed_at);
        $this->assertNotNull($enrollment->certificate_code);
    }

    public function test_optional_quiz_does_not_block_completion(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $this->quiz($course, false, ['is_required' => false]);

        AcademyLearning::completeLesson($user, $course->modules()->first()->lessons()->first());

        $this->assertNotNull(AcademyEnrollment::query()->where('user_id', $user->id)->first()->completed_at);
    }

    public function test_final_quiz_is_required_for_certificate(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $quiz = $this->quiz($course, false);

        AcademyLearning::completeLesson($user, $course->modules()->first()->lessons()->first());
        $this->assertNull(AcademyEnrollment::query()->where('user_id', $user->id)->first()->completed_at);

        $this->actingAs($user)->get(route('academy.courses.show', $course->slug))->assertOk()->assertSee('Évaluation finale')->assertSee('Requis');

        $attempt = AcademyQuizzes::start($user, $quiz);
        AcademyQuizzes::submit($attempt, $this->answers($attempt, 4));

        $this->assertNotNull(AcademyEnrollment::query()->where('user_id', $user->id)->first()->completed_at);
    }

    public function test_attempts_are_private_and_paid_courses_need_access(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $quiz = $this->quiz($this->course());
        $attempt = AcademyQuizzes::start($owner, $quiz);

        $this->actingAs($other)->get(route('academy.attempts.show', $attempt))->assertForbidden();
        $this->actingAs($other)->post(route('academy.attempts.submit', $attempt), ['answers' => []])->assertForbidden();

        $paid = AcademyCourse::query()->create(['title' => 'Payant', 'slug' => 'payant', 'price' => 100, 'status' => 'published']);
        $paidQuiz = AcademyQuiz::query()->create(['course_id' => $paid->id, 'title' => 'Quiz payant', 'pass_percent' => 70]);
        $paidQuiz->questions()->create(['text' => 'Q', 'type' => 'single', 'sort_order' => 1])->options()->createMany([
            ['text' => 'A', 'is_correct' => true, 'sort_order' => 1], ['text' => 'B', 'is_correct' => false, 'sort_order' => 2],
        ]);

        $this->actingAs($other)->get(route('academy.quizzes.show', $paidQuiz))->assertRedirect(route('academy.courses.show', 'payant'));
        $this->actingAs($other)->post(route('academy.quizzes.start', $paidQuiz))->assertRedirect(route('academy.courses.show', 'payant'));
        $this->assertSame(0, AcademyQuizAttempt::query()->where('quiz_id', $paidQuiz->id)->count());
    }

    public function test_quiz_without_questions_cannot_be_started(): void
    {
        $user = User::factory()->create();
        $course = $this->course();
        $quiz = AcademyQuiz::query()->create(['course_id' => $course->id, 'title' => 'Vide', 'pass_percent' => 70]);

        $this->actingAs($user)->post(route('academy.quizzes.start', $quiz))->assertRedirect(route('academy.quizzes.show', $quiz));
        $this->assertSame(0, AcademyQuizAttempt::query()->count());
    }

    public function test_quizzes_passed_trigger_counts_distinct_quizzes(): void
    {
        $user = User::factory()->create();
        $quiz = $this->quiz($this->course());

        foreach ([1, 2] as $ignored) {
            $attempt = AcademyQuizzes::start($user, $quiz);
            AcademyQuizzes::submit($attempt, $this->answers($attempt, 4));
        }

        $this->assertSame(1, (ActivityCatalog::triggers()['academy.quizzes_passed']['count'])($user->id));
    }
}
