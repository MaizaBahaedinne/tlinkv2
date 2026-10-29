<?php

namespace Tests\Feature;

use App\Models\AcademyCourse;
use App\Models\AcademyEnrollment;
use App\Models\AcademyLesson;
use App\Models\AcademyModule;
use App\Models\AcademyOrder;
use App\Models\AcademyPlan;
use App\Models\AcademySession;
use App\Models\AcademySubscription;
use App\Models\Badge;
use App\Models\BadgeMission;
use App\Models\FoundationEvent;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\Academy\AcademyAccess;
use App\Support\Academy\AcademyLearning;
use App\Support\Academy\AcademyOrders;
use App\Support\Academy\AcademySettings;
use App\Support\Gamification\GamificationEngine;
use App\Support\Gamification\StarterBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AcademyTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->grantPlatformAdmin($user);

        return $user;
    }

    /**
     * Cours publié : 2 modules, 2 leçons dans le premier, 1 dans le second.
     *
     * @return array{0: AcademyCourse, 1: array<int, AcademyLesson>}
     */
    private function course(?float $price = null, array $attributes = []): array
    {
        $course = AcademyCourse::query()->create($attributes + [
            'title' => 'Introduction au leadership', 'slug' => 'leadership', 'level' => 'beginner',
            'price' => $price, 'included_in_plans' => true, 'completion_points' => 50, 'status' => 'published',
        ]);

        $one = $course->modules()->create(['title' => 'Bases', 'sort_order' => 1]);
        $two = $course->modules()->create(['title' => 'Pratique', 'sort_order' => 2]);

        $lessons = [
            $one->lessons()->create(['title' => 'Leçon 1', 'kind' => 'text', 'content' => 'Contenu', 'points' => 10, 'sort_order' => 1]),
            $one->lessons()->create(['title' => 'Leçon 2', 'kind' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'points' => 10, 'sort_order' => 2, 'is_preview' => true]),
            $two->lessons()->create(['title' => 'Leçon 3', 'kind' => 'text', 'points' => 20, 'sort_order' => 1]),
        ];

        return [$course, $lessons];
    }

    public function test_free_course_is_open_and_paid_course_is_locked(): void
    {
        $user = User::factory()->create();
        [$free] = $this->course();
        [$paid] = $this->course(150, ['title' => 'Cours payant', 'slug' => 'payant']);

        $this->assertTrue(AcademyAccess::canAccess($user, $free));
        $this->assertFalse(AcademyAccess::canAccess($user, $paid));

        $free->update(['status' => 'draft']);
        $this->assertFalse(AcademyAccess::canAccess($user, $free));
    }

    public function test_preview_lesson_is_viewable_but_other_lessons_of_paid_course_redirect(): void
    {
        $user = User::factory()->create();
        [$paid, $lessons] = $this->course(150);

        $this->actingAs($user)->get('/academy/lecons/'.$lessons[1]->id)->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
        $this->actingAs($user)->get('/academy/lecons/'.$lessons[0]->id)->assertRedirect('/academy/cours/'.$paid->slug);

        // Une leçon d'aperçu ne peut pas être validée sans accès.
        $this->actingAs($user)->post('/academy/lecons/'.$lessons[1]->id.'/terminer')->assertRedirect('/academy/cours/'.$paid->slug);
        $this->assertSame(0, $paid->enrollments()->count());
    }

    public function test_guests_cannot_open_academy_but_certificates_are_public(): void
    {
        $this->get('/academy')->assertRedirect('/connexion');

        $user = User::factory()->create();
        [$course, $lessons] = $this->course();
        foreach ($lessons as $lesson) {
            $this->assertTrue(AcademyLearning::completeLesson($user, $lesson));
        }

        $code = AcademyEnrollment::query()->firstOrFail()->certificate_code;
        $this->get('/certificats/'.$code)->assertOk()->assertSee($user->name)->assertSee('Introduction au leadership')->assertSee($code);
        $this->get('/certificats/TA-0000000000')->assertNotFound();
    }

    public function test_lesson_completion_gives_points_badges_and_certificate(): void
    {
        $user = User::factory()->create();
        [$course, $lessons] = $this->course();
        $moduleBadge = Badge::query()->create(['slug' => 'bases', 'name' => 'Bases acquises', 'tier' => 'bronze']);
        $courseBadge = Badge::query()->create(['slug' => 'leader', 'name' => 'Leader en herbe', 'tier' => 'gold']);
        $course->update(['badge_id' => $courseBadge->id]);
        $course->modules()->orderBy('sort_order')->first()->update(['badge_id' => $moduleBadge->id]);

        $this->actingAs($user)->post('/academy/lecons/'.$lessons[0]->id.'/terminer')->assertRedirect('/academy/lecons/'.$lessons[1]->id);
        $this->assertSame(10, AcademyLearning::points($user->id));
        $this->assertSame(0, UserBadge::query()->count());

        // Terminer deux fois la même leçon ne double pas les points.
        $this->actingAs($user)->post('/academy/lecons/'.$lessons[0]->id.'/terminer');
        $this->assertSame(10, AcademyLearning::points($user->id));

        // Fin du premier module : badge de module.
        $this->actingAs($user)->post('/academy/lecons/'.$lessons[1]->id.'/terminer');
        $this->assertTrue(UserBadge::query()->where('user_id', $user->id)->where('badge_id', $moduleBadge->id)->exists());
        $this->assertFalse(UserBadge::query()->where('badge_id', $courseBadge->id)->exists());
        $this->assertNull(AcademyAccess::enrollment($user, $course)->completed_at);

        // Fin du cours : certificat, points bonus et badge de cours.
        $this->actingAs($user)->post('/academy/lecons/'.$lessons[2]->id.'/terminer')->assertRedirect('/academy/cours/'.$course->slug);

        $enrollment = AcademyAccess::enrollment($user, $course);
        $this->assertNotNull($enrollment->completed_at);
        $this->assertMatchesRegularExpression('/^TA-[A-Z0-9]{10}$/', $enrollment->certificate_code);
        $this->assertSame(40 + 50, AcademyLearning::points($user->id));
        $this->assertTrue(UserBadge::query()->where('user_id', $user->id)->where('badge_id', $courseBadge->id)->exists());
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'academy.course_completed']);

        $this->actingAs($user)->get('/academy/cours/'.$course->slug)->assertOk()->assertSee('Voir mon certificat');
    }

    public function test_academy_triggers_feed_the_gamification_engine(): void
    {
        $user = User::factory()->create();
        [$course, $lessons] = $this->course();

        $badge = Badge::query()->create(['slug' => 'apprenant', 'name' => 'Apprenant', 'tier' => 'bronze']);
        $mission = $badge->missions()->create(['title' => 'Apprendre']);
        $mission->checkpoints()->create(['title' => '2 leçons', 'trigger_key' => 'academy.lessons_completed', 'target' => 2]);

        AcademyLearning::completeLesson($user, $lessons[0]);
        $this->assertFalse(UserBadge::query()->where('badge_id', $badge->id)->exists());

        AcademyLearning::completeLesson($user, $lessons[1]);
        $this->assertTrue(UserBadge::query()->where('user_id', $user->id)->where('badge_id', $badge->id)->exists());
    }

    public function test_starter_academy_badges_are_installed_once(): void
    {
        $this->assertSame(3, StarterBadges::installAcademy());
        $this->assertSame(0, StarterBadges::installAcademy());
        $this->assertSame(3, Badge::query()->whereIn('slug', ['apprenant', 'studieux', 'diplome'])->count());
    }

    public function test_paid_order_unlocks_course_and_refund_removes_access(): void
    {
        $user = User::factory()->create();
        [$course] = $this->course(120);
        AcademySettings::save(['sales_enabled' => true]);

        $response = $this->actingAs($user)->post('/academy/cours/'.$course->slug.'/acheter');
        $order = AcademyOrder::query()->firstOrFail();
        $response->assertRedirect(route('academy.orders.show', $order));
        $this->assertSame('pending', $order->status);
        $this->assertMatchesRegularExpression('/^TA-[A-Z0-9]{8}$/', $order->reference);

        // Une commande en attente est réutilisée.
        $this->actingAs($user)->post('/academy/cours/'.$course->slug.'/acheter');
        $this->assertSame(1, AcademyOrder::query()->count());

        $this->assertFalse(AcademyAccess::canAccess($user, $course));
        $this->actingAs($user)->get('/academy/commandes/'.$order->id)->assertOk()->assertSee($order->reference);

        $this->assertTrue(AcademyOrders::markPaid($order, 'VIR-1'));
        $this->assertFalse(AcademyOrders::markPaid($order->fresh()));
        $this->assertTrue(AcademyAccess::canAccess($user, $course));
        $this->assertSame('purchase', AcademyAccess::enrollment($user, $course)->source);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'academy.order_paid']);

        $this->assertTrue(AcademyOrders::refund($order->fresh()));
        $this->assertFalse(AcademyAccess::canAccess($user, $course));
    }

    public function test_orders_are_private_and_sales_can_be_closed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        [$course] = $this->course(120);

        // Ventes fermées par défaut tant que rien n'est configuré.
        $this->actingAs($owner)->post('/academy/cours/'.$course->slug.'/acheter')->assertRedirect('/academy/cours/'.$course->slug);
        $this->assertSame(0, AcademyOrder::query()->count());

        $order = AcademyOrders::open($owner, $course);
        $this->actingAs($other)->get('/academy/commandes/'.$order->id)->assertNotFound();
    }

    public function test_subscription_unlocks_included_courses_and_extends(): void
    {
        $user = User::factory()->create();
        [$course] = $this->course(120);
        [$excluded] = $this->course(80, ['title' => 'Hors abonnement', 'slug' => 'hors', 'included_in_plans' => false]);
        $plan = AcademyPlan::query()->create(['name' => 'Mensuel', 'slug' => 'mensuel', 'price' => 30, 'duration_days' => 30, 'is_active' => true]);
        AcademySettings::save(['sales_enabled' => true]);

        $this->actingAs($user)->post('/academy/abonnements/'.$plan->id.'/souscrire')->assertRedirect();
        $order = AcademyOrder::query()->where('plan_id', $plan->id)->firstOrFail();
        $this->assertTrue(AcademyOrders::markPaid($order));

        $this->assertTrue(AcademyAccess::hasSubscription($user));
        $this->assertTrue(AcademyAccess::canAccess($user, $course));
        $this->assertFalse(AcademyAccess::canAccess($user, $excluded));

        $firstEnd = AcademySubscription::query()->firstOrFail()->ends_at;
        $second = AcademyOrders::open($user, $plan);
        $this->assertNotSame($order->id, $second->id);
        AcademyOrders::markPaid($second);
        $this->assertTrue(AcademySubscription::query()->orderByDesc('ends_at')->first()->ends_at->greaterThan($firstEnd));

        $this->actingAs($user)->get('/academy')->assertOk()->assertSee('Mensuel');
    }

    public function test_catalog_hides_drafts_and_shows_progress(): void
    {
        $user = User::factory()->create();
        [$course, $lessons] = $this->course();
        [$draft] = $this->course(null, ['title' => 'Brouillon secret', 'slug' => 'secret', 'status' => 'draft']);

        AcademyLearning::completeLesson($user, $lessons[0]);

        $this->actingAs($user)->get('/academy')->assertOk()->assertSee('Introduction au leadership')->assertDontSee('Brouillon secret');
        $this->actingAs($user)->get('/academy/cours/'.$draft->slug)->assertNotFound();
        $this->actingAs($user)->get('/academy/cours/'.$course->slug)->assertOk()->assertSee('Reprendre le cours')->assertSee('Leçon 3');
    }

    public function test_start_leads_to_first_unfinished_lesson(): void
    {
        $user = User::factory()->create();
        [$course, $lessons] = $this->course();

        $this->actingAs($user)->post('/academy/cours/'.$course->slug.'/commencer')->assertRedirect('/academy/lecons/'.$lessons[0]->id);
        AcademyLearning::completeLesson($user, $lessons[0]);
        $this->actingAs($user)->post('/academy/cours/'.$course->slug.'/commencer')->assertRedirect('/academy/lecons/'.$lessons[1]->id);
    }

    public function test_calendar_merges_sessions_and_foundation_events(): void
    {
        $member = User::factory()->create();
        [$course] = $this->course(120);

        AcademySession::query()->create(['title' => 'Webinaire ouvert', 'starts_at' => now()->addDays(2), 'is_published' => true, 'meeting_url' => 'https://meet.example/ouvert']);
        AcademySession::query()->create(['title' => 'Atelier réservé', 'course_id' => $course->id, 'starts_at' => now()->addDays(3), 'is_published' => true, 'meeting_url' => 'https://meet.example/prive']);
        AcademySession::query()->create(['title' => 'Session brouillon', 'starts_at' => now()->addDays(4), 'is_published' => false]);
        AcademySession::query()->create(['title' => 'Session passée', 'starts_at' => now()->subDays(4), 'is_published' => true]);

        $this->seed(\Database\Seeders\GovernorateSeeder::class);
        FoundationEvent::query()->create(['title' => 'Forum Tunivisions', 'status' => 'published', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(3), 'governorate_id' => \App\Models\Governorate::query()->value('id')]);

        $this->actingAs($member)->get('/academy/calendrier')->assertOk()
            ->assertSee('Webinaire ouvert')->assertSee('https://meet.example/ouvert')
            ->assertSee('Atelier réservé')->assertDontSee('https://meet.example/prive')
            ->assertSee('Forum Tunivisions')
            ->assertDontSee('Session brouillon')->assertDontSee('Session passée');

        // Une fois inscrit, le lien de la session du cours apparaît.
        AcademyAccess::enroll($member, $course, 'admin');
        $this->actingAs($member)->get('/academy/calendrier')->assertSee('https://meet.example/prive');
    }

    public function test_admin_manages_courses_modules_and_lessons(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/academy/cours')->assertOk();

        $this->actingAs($admin)->post('/admin/academy/cours', [
            'title' => 'Prise de parole', 'level' => 'intermediate', 'status' => 'published', 'price' => '99.5',
            'included_in_plans' => '1', 'completion_points' => 30, 'cover' => UploadedFile::fake()->image('c.jpg', 600, 300),
        ])->assertSessionHasNoErrors();

        $course = AcademyCourse::query()->where('slug', 'prise-de-parole')->firstOrFail();
        Storage::disk('public')->assertExists($course->cover_path);
        $this->assertSame('99.50', $course->price);

        $this->actingAs($admin)->get('/admin/academy/cours/'.$course->id.'/modifier')->assertOk()->assertSee('Prise de parole');

        $this->actingAs($admin)->post('/admin/academy/cours/'.$course->id.'/modules', ['title' => 'Module A', 'description' => '', 'badge_id' => '', 'sort_order' => ''])->assertSessionHasNoErrors();
        $module = AcademyModule::query()->firstOrFail();
        $this->assertSame(1, $module->sort_order);

        $this->actingAs($admin)->post('/admin/academy/modules/'.$module->id.'/lecons', [
            'title' => 'Vidéo', 'kind' => 'video', 'video_url' => 'https://evil.example/x', 'points' => 5,
        ])->assertSessionHasErrors('video_url');

        $this->actingAs($admin)->post('/admin/academy/modules/'.$module->id.'/lecons', [
            'title' => 'Vidéo', 'kind' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'points' => 5, 'is_preview' => '1', 'sort_order' => '', 'duration_minutes' => '', 'content' => '',
        ])->assertSessionHasNoErrors();
        $lesson = AcademyLesson::query()->firstOrFail();
        $this->assertSame(1, $lesson->sort_order);
        $this->assertTrue($lesson->is_preview);
        $this->actingAs($admin)->get('/admin/academy/cours/'.$course->id.'/modifier')->assertOk()->assertSee('Module A')->assertSee('Aperçu gratuit');
        $this->actingAs($admin)->get('/admin/academy/cours?q=Prise')->assertOk()->assertSee('Prise de parole');

        $this->actingAs($admin)->put('/admin/academy/lecons/'.$lesson->id, ['title' => 'Texte', 'kind' => 'text', 'content' => 'Bonjour', 'points' => 7])->assertSessionHasNoErrors();
        $this->assertNull($lesson->fresh()->video_url);
        $this->assertSame(7, $lesson->fresh()->points);

        $this->actingAs($admin)->delete('/admin/academy/lecons/'.$lesson->id);
        $this->assertSame(0, AcademyLesson::query()->count());

        $this->actingAs($admin)->delete('/admin/academy/cours/'.$course->id);
        $this->assertSame(0, AcademyCourse::query()->count());
        Storage::disk('public')->assertMissing($course->cover_path);
    }

    public function test_course_completed_by_members_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        [$course, $lessons] = $this->course();
        foreach ($lessons as $lesson) {
            AcademyLearning::completeLesson($user, $lesson);
        }

        $this->actingAs($admin)->delete('/admin/academy/cours/'.$course->id)->assertSessionHasErrors('course');
        $this->assertSame(1, AcademyCourse::query()->count());
    }

    public function test_admin_validates_orders_and_members_cannot_use_admin(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        [$course] = $this->course(120);
        $order = AcademyOrders::open($user, $course);

        $this->actingAs($user)->get('/admin/academy/commandes')->assertForbidden();
        $this->actingAs($user)->post('/admin/academy/commandes/'.$order->id.'/payer')->assertForbidden();

        $this->actingAs($admin)->get('/admin/academy/commandes')->assertOk()->assertSee($order->reference);
        $this->actingAs($admin)->post('/admin/academy/commandes/'.$order->id.'/payer', ['provider_reference' => 'ESP-12'])->assertSessionHasNoErrors();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('ESP-12', $order->fresh()->provider_reference);
        $this->assertTrue(AcademyAccess::canAccess($user, $course));

        $this->actingAs($admin)->post('/admin/academy/commandes/'.$order->id.'/annuler')->assertSessionHasErrors('order');
        $this->actingAs($admin)->post('/admin/academy/commandes/'.$order->id.'/rembourser')->assertSessionHasNoErrors();
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertFalse(AcademyAccess::canAccess($user, $course));
    }

    public function test_admin_configures_settings_plans_and_sessions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/academy/reglages')->assertOk();
        $this->actingAs($admin)->put('/admin/academy/reglages', [
            'sales_enabled' => '1', 'gateway' => 'manual', 'manual_instructions' => 'Virement RIB 123', 'contact_email' => 'academy@example.test',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(AcademySettings::get('sales_enabled'));
        $this->assertSame('Virement RIB 123', AcademySettings::get('manual_instructions'));

        $this->actingAs($admin)->put('/admin/academy/reglages', ['gateway' => 'inconnu'])->assertSessionHasErrors('gateway');

        $this->actingAs($admin)->post('/admin/academy/reglages/abonnements', ['name' => 'Annuel', 'price' => '250', 'duration_days' => 365, 'is_active' => '1'])->assertSessionHasNoErrors();
        $plan = AcademyPlan::query()->firstOrFail();
        $this->assertSame('annuel', $plan->slug);
        $this->actingAs($admin)->get('/admin/academy/reglages')->assertOk()->assertSee('Annuel');

        $this->actingAs($admin)->put('/admin/academy/reglages/abonnements/'.$plan->id, ['name' => 'Annuel Pro', 'price' => '300', 'duration_days' => 365])->assertSessionHasNoErrors();
        $this->assertFalse($plan->fresh()->is_active);

        $this->actingAs($admin)->get('/admin/academy/sessions')->assertOk();
        $this->actingAs($admin)->post('/admin/academy/sessions', [
            'title' => 'Live de rentrée', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('ends_at');

        $this->actingAs($admin)->post('/admin/academy/sessions', [
            'title' => 'Live de rentrée', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'meeting_url' => 'https://meet.example/x', 'is_published' => '1',
        ])->assertSessionHasNoErrors();
        $session = AcademySession::query()->firstOrFail();
        $this->assertTrue($session->is_published);
        $this->actingAs($admin)->get('/admin/academy/sessions')->assertOk()->assertSee('Live de rentrée');

        $this->actingAs($admin)->delete('/admin/academy/sessions/'.$session->id);
        $this->assertSame(0, AcademySession::query()->count());

        $this->actingAs($admin)->delete('/admin/academy/reglages/abonnements/'.$plan->id);
        $this->assertSame(0, AcademyPlan::query()->count());
    }

    private function issuedCertificateCode(): string
    {
        $user = User::factory()->create();
        [, $lessons] = $this->course();
        foreach ($lessons as $lesson) {
            AcademyLearning::completeLesson($user, $lesson);
        }

        return AcademyEnrollment::query()->firstOrFail()->certificate_code;
    }

    public function test_certificate_shows_foundation_logo_and_default_signature_titles(): void
    {
        $code = $this->issuedCertificateCode();

        $this->get('/certificats/'.$code)->assertOk()
            ->assertSee('logoFondation')
            ->assertSee('Chef de projet Tunivisions Academy')
            ->assertSee('Président de la Fondation Tunivisions')
            ->assertDontSee('storage/academy/certificate');
    }

    public function test_admin_configures_certificate_logo_and_signatures(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/academy/reglages/certificat', [
            'logo' => UploadedFile::fake()->image('logo.png'),
            'manager_signature' => UploadedFile::fake()->image('chef.png'),
            'president_signature' => UploadedFile::fake()->image('president.webp'),
            'manager_name' => 'Sami Ben Ali',
            'manager_title' => 'Chef de projet',
            'president_name' => 'Leila Trabelsi',
            'president_title' => 'Présidente de la Fondation',
        ])->assertSessionHasNoErrors();

        $settings = AcademySettings::all();
        foreach (['logo_path', 'manager_signature_path', 'president_signature_path'] as $key) {
            $this->assertStringStartsWith('academy/certificate/', $settings[$key]);
            Storage::disk('public')->assertExists($settings[$key]);
        }

        $code = $this->issuedCertificateCode();
        $this->get('/certificats/'.$code)->assertOk()
            ->assertSee('storage/academy/certificate', false)
            ->assertSee('Sami Ben Ali')
            ->assertSee('Leila Trabelsi')
            ->assertSee('Présidente de la Fondation');

        $oldLogo = $settings['logo_path'];
        $this->actingAs($admin)->put('/admin/academy/reglages/certificat', [
            'remove_logo' => '1',
            'manager_name' => 'Sami Ben Ali',
            'manager_title' => 'Chef de projet',
            'president_name' => 'Leila Trabelsi',
            'president_title' => 'Présidente de la Fondation',
        ])->assertSessionHasNoErrors();

        $this->assertSame('', AcademySettings::get('logo_path'));
        Storage::disk('public')->assertMissing($oldLogo);
        $this->assertNotSame('', AcademySettings::get('manager_signature_path'));
    }

    public function test_certificate_settings_reject_non_admins_and_invalid_files(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->put('/admin/academy/reglages/certificat', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->put('/admin/academy/reglages/certificat', ['logo' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('logo');
        $this->assertSame('', AcademySettings::get('logo_path'));
    }

    public function test_engine_award_is_idempotent(): void
    {
        $user = User::factory()->create();
        $badge = Badge::query()->create(['slug' => 'direct', 'name' => 'Direct', 'tier' => 'bronze']);

        $this->assertTrue(GamificationEngine::award($user->id, $badge->fresh()));
        $this->assertFalse(GamificationEngine::award($user->id, $badge->fresh()));
        $this->assertSame(1, UserBadge::query()->count());
    }
}
