<?php

namespace Tests\Feature;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\InfoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class InfoSessionTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    /**
     * Réponses de l'instantané : toutes justes, ou en rendant fausse la question $wrongIndex.
     *
     * @return array<string, array<int, string>>
     */
    private function answersFor(QuizAttempt $attempt, ?int $wrongIndex = null): array
    {
        $answers = [];

        foreach ($attempt->snapshot['questions'] as $index => $question) {
            $correct = $attempt->snapshot['correct'][$question['id']];

            if ($index === $wrongIndex) {
                $wrong = collect($question['options'])->pluck('id')->diff($correct)->first();
                $answers[$question['id']] = [$wrong];

                continue;
            }

            $answers[$question['id']] = $correct;
        }

        return $answers;
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/info-session')->assertRedirect('/connexion');
        $this->post('/info-session/quiz')->assertRedirect('/connexion');
    }

    public function test_page_shows_the_video_and_a_locked_quiz_button(): void
    {
        $page = $this->actingAs(User::factory()->create())->get('/info-session')
            ->assertOk()
            ->assertSee('Info session et quiz')
            ->assertSee('QwrBWK07RnU');

        $this->assertMatchesRegularExpression('/id="start-quiz"\s+disabled/', $page->getContent());
    }

    public function test_unvalidated_members_see_a_banner_until_they_pass_the_quiz(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profil')->assertOk()->assertSee('Compte non validé');
        $this->actingAs($user)->get('/info-session')->assertDontSee('Compte non validé');

        $user->forceFill(['onboarding_completed_at' => now()])->save();

        $this->actingAs($user)->get('/profil')->assertDontSee('Compte non validé');
    }

    public function test_the_quiz_page_never_exposes_the_correct_answers_and_reuses_the_open_attempt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/info-session/quiz')->assertRedirect();
        $attempt = QuizAttempt::query()->firstOrFail();

        $page = $this->actingAs($user)->get('/info-session/quiz/'.$attempt->id)->assertOk();
        $page->assertSee('Une fondation nationale')->assertSee('Plusieurs réponses possibles');
        $this->assertStringNotContainsString('"correct"', $page->getContent());

        $this->actingAs($user)->post('/info-session/quiz')->assertRedirect('/info-session/quiz/'.$attempt->id);
        $this->assertSame(1, QuizAttempt::query()->count());
        $this->assertSame(6, $attempt->total);
    }

    public function test_questions_and_answers_are_shuffled_between_attempts(): void
    {
        $orders = [];
        $optionOrders = [];

        foreach (range(1, 8) as $i) {
            $user = User::factory()->create();
            $attempt = InfoSession::start($user);
            $orders[] = collect($attempt->snapshot['questions'])->pluck('id')->implode(',');
            $optionOrders[] = collect($attempt->snapshot['questions'][0]['options'])->pluck('text')->implode('|');
        }

        $this->assertGreaterThan(1, count(array_unique($orders)));
        $this->assertGreaterThan(1, count(array_unique($optionOrders)));
    }

    public function test_all_correct_answers_validate_the_account(): void
    {
        $user = User::factory()->create();
        $attempt = InfoSession::start($user);

        $this->actingAs($user)->post('/info-session/quiz/'.$attempt->id, ['answers' => $this->answersFor($attempt)])
            ->assertRedirect('/info-session');

        $attempt->refresh();
        $this->assertSame('passed', $attempt->status);
        $this->assertSame(6, $attempt->score);
        $this->assertNotNull($user->fresh()->onboarding_completed_at);

        $this->actingAs($user)->get('/info-session')->assertSee('Bravo, quiz réussi : 6 / 6');
    }

    public function test_one_wrong_answer_fails_with_the_default_threshold_and_allows_a_retry(): void
    {
        $user = User::factory()->create();
        $attempt = InfoSession::start($user);

        $this->actingAs($user)->post('/info-session/quiz/'.$attempt->id, ['answers' => $this->answersFor($attempt, 0)]);

        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame(5, $attempt->fresh()->score);
        $this->assertNull($user->fresh()->onboarding_completed_at);

        $this->actingAs($user)->post('/info-session/quiz')->assertRedirect();
        $this->assertSame(2, QuizAttempt::query()->count());
    }

    public function test_a_multiple_choice_question_needs_exactly_the_right_set(): void
    {
        $user = User::factory()->create();
        $attempt = InfoSession::start($user);
        $answers = $this->answersFor($attempt);
        $multiple = collect($attempt->snapshot['questions'])->firstWhere('type', 'multiple');
        $extra = collect($multiple['options'])->pluck('id')->diff($attempt->snapshot['correct'][$multiple['id']])->first();

        // Une bonne réponse en trop : la question est fausse.
        $answers[$multiple['id']][] = $extra;
        $this->actingAs($user)->post('/info-session/quiz/'.$attempt->id, ['answers' => $answers]);
        $this->assertSame(5, $attempt->fresh()->score);

        // Une bonne réponse en moins : fausse aussi.
        $second = InfoSession::start($user);
        $answers = $this->answersFor($second);
        $multiple = collect($second->snapshot['questions'])->firstWhere('type', 'multiple');
        array_pop($answers[$multiple['id']]);
        $this->actingAs($user)->post('/info-session/quiz/'.$second->id, ['answers' => $answers]);
        $this->assertSame(5, $second->fresh()->score);
    }

    public function test_the_threshold_comes_from_the_settings(): void
    {
        InfoSession::save(array_merge(InfoSession::settings(), ['pass_percent' => 80]));
        $user = User::factory()->create();
        $attempt = InfoSession::start($user);

        $this->actingAs($user)->post('/info-session/quiz/'.$attempt->id, ['answers' => $this->answersFor($attempt, 0)]);

        $this->assertSame('passed', $attempt->fresh()->status);
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_a_late_submission_is_not_graded(): void
    {
        $user = User::factory()->create();
        $attempt = InfoSession::start($user);
        $attempt->update(['expires_at' => now()->subSeconds(InfoSession::GRACE_SECONDS + 5)]);

        $this->actingAs($user)->post('/info-session/quiz/'.$attempt->id, ['answers' => $this->answersFor($attempt)]);

        $this->assertSame('expired', $attempt->fresh()->status);
        $this->assertNull($user->fresh()->onboarding_completed_at);
    }

    public function test_nobody_can_open_or_submit_someone_elses_attempt(): void
    {
        $owner = User::factory()->create();
        $attempt = InfoSession::start($owner);
        $other = User::factory()->create();

        $this->actingAs($other)->get('/info-session/quiz/'.$attempt->id)->assertForbidden();
        $this->actingAs($other)->post('/info-session/quiz/'.$attempt->id, ['answers' => $this->answersFor($attempt)])->assertForbidden();
        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    public function test_a_submitted_attempt_cannot_be_replayed(): void
    {
        $user = User::factory()->create();
        $attempt = InfoSession::start($user);
        $this->actingAs($user)->post('/info-session/quiz/'.$attempt->id, ['answers' => $this->answersFor($attempt, 0)]);

        $this->actingAs($user)->post('/info-session/quiz/'.$attempt->id, ['answers' => $this->answersFor($attempt)]);

        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->actingAs($user)->get('/info-session/quiz/'.$attempt->id)->assertRedirect('/info-session');
    }

    public function test_admin_edits_the_session_and_the_quiz_uses_it(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $this->actingAs($admin)->get('/admin/info-session')->assertOk()->assertSee('Une fondation nationale');

        $this->actingAs($admin)->put('/admin/info-session', [
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'title' => 'Bienvenue',
            'intro' => 'Intro',
            'duration_minutes' => 5,
            'pass_percent' => 50,
            'questions' => [
                ['id' => 'a', 'text' => 'Capitale de la Tunisie ?', 'type' => 'single', 'options' => [
                    ['text' => 'Tunis', 'correct' => '1'], ['text' => 'Sfax'], ['text' => ''],
                ]],
                ['text' => '', 'options' => []],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/info-session');

        $settings = InfoSession::settings();
        $this->assertSame(5, $settings['duration_minutes']);
        $this->assertCount(1, $settings['questions']);
        $this->assertCount(2, $settings['questions'][0]['options']);

        $user = User::factory()->create();
        $attempt = InfoSession::start($user);
        $this->assertSame(1, $attempt->total);
        $this->assertSame(5, (int) round($attempt->started_at->diffInSeconds($attempt->expires_at) / 60));
        $this->actingAs($user)->get('/info-session')->assertSee('Bienvenue')->assertSee('dQw4w9WgXcQ');
    }

    public function test_admin_validation_rejects_inconsistent_questions_and_bad_links(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $base = ['video_url' => 'https://www.youtube.com/watch?v=QwrBWK07RnU', 'title' => 'T', 'duration_minutes' => 8, 'pass_percent' => 100];

        $this->actingAs($admin)->put('/admin/info-session', $base + ['questions' => [['text' => 'Sans bonne réponse', 'options' => [['text' => 'A'], ['text' => 'B']]]]])
            ->assertSessionHasErrors('questions.1');

        $this->actingAs($admin)->put('/admin/info-session', $base + ['questions' => [['text' => 'Unique', 'type' => 'single', 'options' => [['text' => 'A', 'correct' => '1'], ['text' => 'B', 'correct' => '1']]]]])
            ->assertSessionHasErrors('questions.1');

        $this->actingAs($admin)->put('/admin/info-session', ['video_url' => 'https://example.com/video'] + $base + ['questions' => [['text' => 'Q', 'options' => [['text' => 'A', 'correct' => '1'], ['text' => 'B']]]]])
            ->assertSessionHasErrors('video_url');

        $this->actingAs($admin)->put('/admin/info-session', $base)->assertSessionHasErrors('questions');
    }

    public function test_only_global_admins_can_edit(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/info-session')->assertForbidden();
        $this->actingAs(User::factory()->create())->put('/admin/info-session', [])->assertForbidden();

        $manager = User::factory()->create();
        $this->grantAccess($manager, 'entity_admin', 'university');
        $this->actingAs($manager)->get('/admin/info-session')->assertOk();
        $this->actingAs($manager)->put('/admin/info-session', ['video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'X', 'duration_minutes' => 5, 'pass_percent' => 50])->assertForbidden();
    }

    public function test_video_id_extraction(): void
    {
        $this->assertSame('QwrBWK07RnU', InfoSession::videoId('https://www.youtube.com/watch?v=QwrBWK07RnU'));
        $this->assertSame('QwrBWK07RnU', InfoSession::videoId('https://youtu.be/QwrBWK07RnU?t=3'));
        $this->assertSame('QwrBWK07RnU', InfoSession::videoId('https://www.youtube.com/embed/QwrBWK07RnU'));
        $this->assertNull(InfoSession::videoId('https://evil.test/watch?v=QwrBWK07RnU'));
        $this->assertNull(InfoSession::videoId('https://www.youtube.com/watch?v=court'));
        $this->assertNull(InfoSession::videoId(null));
    }
}
