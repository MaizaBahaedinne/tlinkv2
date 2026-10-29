<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Badge;
use App\Models\Mission;
use App\Models\MissionCheckpoint;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\Gamification\ActivityCatalog;
use App\Support\Gamification\GamificationEngine;
use App\Support\Gamification\StarterBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    /** Badge « Orateur » : une mission, deux points de contrôle (2 posts et la photo de profil). */
    private function speakerBadge(string $tier = 'silver'): Badge
    {
        $badge = Badge::query()->create(['slug' => 'orateur', 'name' => 'Orateur', 'tier' => $tier, 'description' => 'Prendre la parole.']);
        $mission = $badge->missions()->create(['title' => 'Publier', 'sort_order' => 1]);
        $mission->checkpoints()->create(['title' => 'Publier 2 posts', 'trigger_key' => 'post.created', 'target' => 2, 'sort_order' => 1]);
        $mission->checkpoints()->create(['title' => 'Avoir une photo', 'trigger_key' => 'profile.photo', 'target' => 1, 'sort_order' => 2]);

        return $badge;
    }

    private function publish(User $user, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            Post::query()->create(['user_id' => $user->id, 'type' => 'text', 'visibility' => 'authenticated', 'content' => 'Bonjour '.$i, 'comments_enabled' => true, 'published_at' => now()]);
        }
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    public function test_progress_is_computed_from_activity_and_capped_at_the_target(): void
    {
        $badge = $this->speakerBadge();
        $user = User::factory()->create();
        $this->publish($user, 5);

        $row = GamificationEngine::progress($user->id)[0];

        $this->assertSame($badge->id, $row['badge']->id);
        $this->assertFalse($row['earned']);
        $this->assertSame(1, $row['done']);
        $this->assertSame(2, $row['total']);
        $this->assertSame(50, $row['percent']);
        $this->assertSame(2, $row['missions'][0]['checkpoints'][0]['current']);
        $this->assertFalse($row['missions'][0]['done']);
    }

    public function test_badge_is_awarded_once_when_every_checkpoint_is_reached_and_the_member_is_notified(): void
    {
        $badge = $this->speakerBadge('gold');
        $user = User::factory()->create();

        $this->publish($user, 2);
        $this->assertDatabaseCount('user_badges', 0);

        Profile::query()->create(['user_id' => $user->id, 'photo_path' => 'avatars/moi.png']);

        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id, 'badge_id' => $badge->id]);
        $notification = AppNotification::query()->where('user_id', $user->id)->where('type', 'badge.awarded')->get();
        $this->assertCount(1, $notification);
        $this->assertStringContainsString('Orateur', $notification->first()->title);

        $this->publish($user, 3);
        GamificationEngine::refresh($user->id);

        $this->assertSame(1, UserBadge::query()->count());
        $this->assertSame(1, AppNotification::query()->where('type', 'badge.awarded')->count());
    }

    public function test_an_earned_badge_stays_earned_when_the_activity_disappears(): void
    {
        $this->speakerBadge();
        $user = User::factory()->create();
        Profile::query()->create(['user_id' => $user->id, 'photo_path' => 'avatars/moi.png']);
        $this->publish($user, 2);
        $this->assertSame(1, UserBadge::query()->count());

        Post::query()->where('user_id', $user->id)->delete();

        $row = GamificationEngine::progress($user->id)[0];
        $this->assertTrue($row['earned']);
        $this->assertSame(100, $row['percent']);
    }

    public function test_inactive_badges_and_empty_badges_are_never_awarded(): void
    {
        $inactive = $this->speakerBadge();
        $inactive->update(['is_active' => false]);
        Badge::query()->create(['slug' => 'vide', 'name' => 'Vide', 'tier' => 'bronze']);
        $user = User::factory()->create();
        Profile::query()->create(['user_id' => $user->id, 'photo_path' => 'avatars/moi.png']);
        $this->publish($user, 2);

        GamificationEngine::refresh($user->id);

        $this->assertDatabaseCount('user_badges', 0);
        $this->assertSame([], array_filter(GamificationEngine::progress($user->id), fn (array $row): bool => $row['badge']->slug === 'orateur'));
    }

    public function test_sync_command_catches_up_the_past_activity(): void
    {
        $user = User::factory()->create();
        $this->publish($user, 2);
        Profile::query()->create(['user_id' => $user->id, 'photo_path' => 'avatars/moi.png']);
        $this->speakerBadge();
        $this->assertDatabaseCount('user_badges', 0);

        $this->artisan('gamification:sync')->assertSuccessful();

        $this->assertDatabaseCount('user_badges', 1);
    }

    public function test_every_trigger_of_the_catalog_has_a_working_counter(): void
    {
        $user = User::factory()->create();

        foreach (array_keys(ActivityCatalog::triggers()) as $key) {
            $this->assertSame(0, ActivityCatalog::count($key, $user->id), $key);
        }

        $this->assertSame(0, ActivityCatalog::count('inconnu', $user->id));
    }

    public function test_starter_badges_are_installed_once_with_valid_triggers(): void
    {
        $this->assertTrue(StarterBadges::install());
        $this->assertFalse(StarterBadges::install());

        $this->assertSame(['bronze', 'bronze', 'silver', 'gold', 'platinum'], Badge::query()->orderBy('sort_order')->pluck('tier')->all());

        foreach (MissionCheckpoint::query()->pluck('trigger_key')->unique() as $key) {
            $this->assertTrue(ActivityCatalog::has($key), $key);
        }
    }

    public function test_member_page_lists_badges_with_missions_and_progress(): void
    {
        $this->speakerBadge();
        $user = User::factory()->create();
        $this->publish($user, 1);

        $this->actingAs($user)->get('/badges')
            ->assertOk()
            ->assertSee('Orateur')
            ->assertSee('Argent')
            ->assertSee('Publier 2 posts')
            ->assertSee('1 / 2')
            ->assertSee('En cours');
    }

    public function test_member_page_requires_login(): void
    {
        $this->get('/badges')->assertRedirect();
    }

    public function test_earned_badges_show_on_the_profile_and_in_the_feed(): void
    {
        $badge = $this->speakerBadge('platinum');
        $user = User::factory()->create();
        UserBadge::query()->create(['user_id' => $user->id, 'badge_id' => $badge->id, 'awarded_at' => now()]);

        $this->actingAs($user)->get('/profil')->assertOk()->assertSee('Orateur')->assertSee('Platine');
        $this->actingAs($user)->get('/feed')->assertOk()->assertSee('myBadges: [{"id":'.$badge->id, false)->assertSee('Orateur');

        $other = User::factory()->create();
        $this->actingAs($other)->get('/profil')->assertOk()->assertDontSee('Orateur');
    }

    public function test_admin_manages_badges_missions_and_checkpoints(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/badges', ['name' => 'Ambassadeur', 'tier' => 'gold', 'icon' => 'fa-solid fa-star', 'is_active' => '1'])->assertSessionHasNoErrors();
        $badge = Badge::query()->firstOrFail();
        $this->assertSame('ambassadeur', $badge->slug);
        $this->assertSame('gold', $badge->tier);

        $this->get('/admin/badges')->assertOk()->assertSee('Ambassadeur');
        $this->get("/admin/badges/{$badge->id}/modifier")->assertOk();

        $this->post("/admin/badges/{$badge->id}/missions", ['title' => 'Parler de T.Link'])->assertSessionHasNoErrors();
        $mission = Mission::query()->firstOrFail();

        $this->post("/admin/badges/missions/{$mission->id}/points", ['title' => 'Publier 3 posts', 'trigger_key' => 'post.created', 'target' => 3])->assertSessionHasNoErrors();
        $checkpoint = MissionCheckpoint::query()->firstOrFail();
        $this->assertSame(3, $checkpoint->target);

        $this->get("/admin/badges/{$badge->id}/modifier")->assertOk()->assertSee('Publier 3 posts')->assertSee('Publications publiées');

        $this->put("/admin/badges/points/{$checkpoint->id}", ['title' => 'Publier 4 posts', 'trigger_key' => 'post.created', 'target' => 4])->assertSessionHasNoErrors();
        $this->assertSame(4, $checkpoint->fresh()->target);

        $this->put("/admin/badges/missions/{$mission->id}", ['title' => 'Mission renommée', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($mission->fresh()->is_active);

        $this->put("/admin/badges/{$badge->id}", ['name' => 'Ambassadeur+', 'tier' => 'platinum', 'sort_order' => 3])->assertSessionHasNoErrors();
        $this->assertSame('platinum', $badge->fresh()->tier);
        $this->assertFalse($badge->fresh()->is_active);

        $this->delete("/admin/badges/points/{$checkpoint->id}")->assertSessionHasNoErrors();
        $this->delete("/admin/badges/missions/{$mission->id}")->assertSessionHasNoErrors();
        $this->delete("/admin/badges/{$badge->id}")->assertRedirect(route('admin.badges.index'));

        $this->assertDatabaseCount('badges', 0);
        $this->assertDatabaseCount('missions', 0);
        $this->assertDatabaseCount('mission_checkpoints', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.badge.created']);
    }

    public function test_admin_input_is_validated(): void
    {
        $this->actingAs($this->admin());
        $badge = $this->speakerBadge();
        $mission = $badge->missions()->firstOrFail();

        $this->post('/admin/badges', ['name' => 'X', 'tier' => 'diamond'])->assertSessionHasErrors('tier');
        $this->post('/admin/badges', ['name' => 'X', 'tier' => 'gold', 'icon' => '"><script>'])->assertSessionHasErrors('icon');
        $this->post("/admin/badges/missions/{$mission->id}/points", ['title' => 'Faux', 'trigger_key' => 'inexistant', 'target' => 1])->assertSessionHasErrors('trigger_key');
        $this->post("/admin/badges/missions/{$mission->id}/points", ['title' => 'Zéro', 'trigger_key' => 'post.created', 'target' => 0])->assertSessionHasErrors('target');
    }

    public function test_admin_badge_list_loads_the_icon_font_so_medals_are_visible(): void
    {
        $this->actingAs($this->admin());
        $this->speakerBadge();

        $this->get('/admin/badges')->assertOk()->assertSee('Orateur')->assertSee('admin-icons', false);
    }

    public function test_admin_can_recalculate_every_member(): void
    {
        $this->actingAs($this->admin());
        $member = User::factory()->create();
        $this->publish($member, 2);
        Profile::query()->create(['user_id' => $member->id, 'photo_path' => 'avatars/moi.png']);
        $this->speakerBadge();

        $this->post('/admin/badges/recalculer')->assertSessionHas('status');

        $this->assertDatabaseHas('user_badges', ['user_id' => $member->id]);
    }

    public function test_recalculation_reports_members_in_error_instead_of_failing(): void
    {
        $this->actingAs($this->admin());
        User::factory()->create();

        $badge = Badge::query()->create(['slug' => 'cassé', 'name' => 'Cassé', 'tier' => 'bronze']);
        $badge->missions()->create(['title' => 'M'])->checkpoints()->create(['title' => 'C', 'trigger_key' => 'academy.lessons_completed', 'target' => 1]);
        \Illuminate\Support\Facades\Schema::drop('academy_lesson_completions');

        $this->post('/admin/badges/recalculer')->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'en erreur'));
    }

    public function test_non_admin_cannot_manage_badges(): void
    {
        $badge = $this->speakerBadge();
        $this->actingAs(User::factory()->create());

        $this->get('/admin/badges')->assertForbidden();
        $this->post('/admin/badges', ['name' => 'X', 'tier' => 'gold'])->assertForbidden();
        $this->delete("/admin/badges/{$badge->id}")->assertForbidden();
        $this->assertDatabaseCount('badges', 1);
    }
}
