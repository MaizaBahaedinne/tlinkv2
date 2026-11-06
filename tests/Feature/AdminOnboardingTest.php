<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\InfoSession;
use App\Support\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AdminOnboardingTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['name' => 'Karim Admin']);
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    public function test_the_page_lists_each_account_with_its_step_and_progress(): void
    {
        $unverified = User::factory()->inOnboarding()->create(['name' => 'Amel Nonverifiee', 'email_verified_at' => null]);
        $quiz = User::factory()->inOnboarding()->create(['name' => 'Bilel Quiz', 'email_verified_at' => now()]);
        $history = User::factory()->inOnboarding()->create(['name' => 'Chaima Accueil', 'email_verified_at' => now(), 'onboarding_completed_at' => now()]);
        User::factory()->create(['name' => 'Dora Terminee']);

        $attempt = InfoSession::start($quiz);
        $attempt->update(['status' => 'failed', 'score' => 4, 'total' => 6, 'submitted_at' => now()]);

        $this->actingAs($this->admin())->get('/admin/integration')->assertOk()
            ->assertSee('Intégration des membres')
            ->assertSee('Amel Nonverifiee')
            ->assertSee('Bilel Quiz')
            ->assertSee('Chaima Accueil')
            ->assertDontSee('Dora Terminee')
            ->assertSee('bloqué à « Vérifier mon e-mail »', false)
            ->assertSee('bloqué à « Valider la session d’information »', false)
            ->assertSee('bloqué à « Ancien ou nouveau membre »', false)
            ->assertSee('4 / 6');

        $this->assertNotNull($unverified->id);
    }

    public function test_the_filters_split_in_progress_done_and_blocking_step(): void
    {
        User::factory()->inOnboarding()->create(['name' => 'Amel Nonverifiee', 'email_verified_at' => null]);
        User::factory()->inOnboarding()->create(['name' => 'Bilel Quiz', 'email_verified_at' => now()]);
        User::factory()->create(['name' => 'Dora Terminee']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/integration?status=done')->assertOk()->assertSee('Dora Terminee')->assertDontSee('Bilel Quiz');
        $this->actingAs($admin)->get('/admin/integration?status=all')->assertOk()->assertSee('Dora Terminee')->assertSee('Bilel Quiz');
        $this->actingAs($admin)->get('/admin/integration?step=email')->assertOk()->assertSee('Amel Nonverifiee')->assertDontSee('Bilel Quiz');
        $this->actingAs($admin)->get('/admin/integration?step=info_session')->assertOk()->assertSee('Bilel Quiz')->assertDontSee('Amel Nonverifiee');
        $this->actingAs($admin)->get('/admin/integration?q=bilel')->assertOk()->assertSee('Bilel Quiz')->assertDontSee('Amel Nonverifiee');
    }

    public function test_an_admin_restarts_a_member_who_is_already_through(): void
    {
        $member = User::factory()->create(['email_verified_at' => now(), 'onboarding_completed_at' => now(), 'history_onboarding_at' => now(), 'member_origin' => 'alumni']);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/integration/'.$member->id.'/relancer')->assertRedirect();

        $member->refresh();
        $this->assertNull($member->onboarding_done_at);
        $this->assertNull($member->onboarding_completed_at);
        $this->assertNull($member->history_onboarding_at);
        $this->assertNull($member->member_origin);
        $this->assertNotNull($member->email_verified_at);
        $this->assertSame('info_session', Onboarding::progress($member)['current']);
        $this->assertTrue(AuditLog::query()->where('action', 'admin.onboarding.restarted')->where('target_id', $member->id)->exists());

        $this->actingAs($member)->get('/feed')->assertRedirect(route('integration.show'));
    }

    public function test_the_email_can_be_revalidated_too(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($this->admin())->post('/admin/integration/'.$member->id.'/relancer', ['revalidate_email' => 1])->assertRedirect();

        $this->assertNull($member->fresh()->email_verified_at);
        $this->assertSame('email', Onboarding::progress($member->fresh())['current']);
    }

    public function test_an_admin_cannot_restart_his_own_onboarding(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/integration/'.$admin->id.'/relancer')->assertForbidden();

        $this->assertNotNull($admin->fresh()->onboarding_done_at);
    }

    public function test_regular_members_cannot_see_or_restart(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($member)->get('/admin/integration')->assertForbidden();
        $this->actingAs($member)->post('/admin/integration/'.$other->id.'/relancer')->assertForbidden();
        $this->assertNotNull($other->fresh()->onboarding_done_at);
    }

    public function test_the_progress_does_not_mark_the_account_as_done(): void
    {
        $member = User::factory()->inOnboarding()->create(['email_verified_at' => now()]);

        $progress = Onboarding::progress($member);

        $this->assertFalse($progress['complete']);
        $this->assertSame('info_session', $progress['current']);
        $this->assertNull($member->fresh()->onboarding_done_at);
        $this->assertSame(0, QuizAttempt::query()->count());
    }

    public function test_the_admin_menu_links_to_the_page(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertSee('Intégration des membres')->assertSee('/admin/integration', false);
    }
}
