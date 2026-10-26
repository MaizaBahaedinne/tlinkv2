<?php

namespace Tests\Feature;

use App\Models\AppModule;
use App\Models\User;
use App\Support\ModuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AppModulesTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function globalAdmin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    public function test_catalog_is_installed_with_modules_and_sub_modules(): void
    {
        $this->assertGreaterThan(0, AppModule::query()->whereNull('parent_id')->count());

        $posts = AppModule::query()->where('key', 'feed.posts')->firstOrFail();
        $this->assertSame('feed', $posts->parent->key);
        $this->assertSame(['create', 'read', 'update', 'delete'], $posts->supportedActions());
        $this->assertSame(['read'], AppModule::query()->where('key', 'governance.audit')->firstOrFail()->supportedActions());
    }

    public function test_sync_only_adds_missing_modules_and_never_overwrites_edits(): void
    {
        AppModule::query()->where('key', 'feed.posts')->update(['name' => 'Posts renommés']);
        AppModule::query()->where('key', 'feed.shares')->delete();

        $this->assertSame(1, ModuleCatalog::sync());
        $this->assertSame('Posts renommés', AppModule::query()->where('key', 'feed.posts')->value('name'));
        $this->assertTrue(AppModule::query()->where('key', 'feed.shares')->exists());
    }

    public function test_global_admin_adds_edits_and_deletes_a_custom_sub_module(): void
    {
        $this->actingAs($this->globalAdmin());
        $feed = AppModule::query()->where('key', 'feed')->firstOrFail();

        $this->post('/admin/modules', ['parent_id' => $feed->id, 'key' => 'feed.polls', 'name' => 'Sondages', 'actions' => ['create', 'read'], 'is_active' => '1'])->assertSessionHasNoErrors();
        $poll = AppModule::query()->where('key', 'feed.polls')->firstOrFail();
        $this->assertSame($feed->id, $poll->parent_id);
        $this->assertSame(['create', 'read'], $poll->supportedActions());

        $this->put('/admin/modules/'.$poll->id, ['name' => 'Sondages 2', 'parent_id' => $feed->id, 'actions' => ['read']])->assertSessionHasNoErrors();
        $poll->refresh();
        $this->assertSame('Sondages 2', $poll->name);
        $this->assertSame(['read'], $poll->supportedActions());
        $this->assertFalse($poll->is_active);
        $this->assertSame('feed.polls', $poll->key);

        $this->delete('/admin/modules/'.$poll->id)->assertSessionHasNoErrors();
        $this->assertFalse(AppModule::query()->where('key', 'feed.polls')->exists());
    }

    public function test_system_modules_and_modules_with_children_cannot_be_deleted(): void
    {
        $this->actingAs($this->globalAdmin());

        $system = AppModule::query()->where('key', 'feed.posts')->firstOrFail();
        $this->delete('/admin/modules/'.$system->id)->assertSessionHasErrors('module');

        $custom = AppModule::query()->create(['key' => 'custom', 'name' => 'Custom', 'actions' => []]);
        AppModule::query()->create(['key' => 'custom.child', 'name' => 'Enfant', 'parent_id' => $custom->id, 'actions' => ['read']]);
        $this->delete('/admin/modules/'.$custom->id)->assertSessionHasErrors('module');

        $this->assertTrue(AppModule::query()->where('key', 'feed.posts')->exists());
        $this->assertTrue(AppModule::query()->where('key', 'custom')->exists());
    }

    public function test_only_two_levels_and_valid_unique_keys(): void
    {
        $this->actingAs($this->globalAdmin());
        $child = AppModule::query()->where('key', 'feed.posts')->firstOrFail();

        $this->post('/admin/modules', ['parent_id' => $child->id, 'key' => 'feed.posts.deep', 'name' => 'Trop profond'])->assertSessionHasErrors('parent_id');
        $this->post('/admin/modules', ['key' => 'Cle Invalide', 'name' => 'X'])->assertSessionHasErrors('key');
        $this->post('/admin/modules', ['key' => 'feed', 'name' => 'Doublon'])->assertSessionHasErrors('key');
    }

    public function test_non_admin_cannot_manage_modules(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/modules')->assertForbidden();
        $this->post('/admin/modules', ['key' => 'x', 'name' => 'X'])->assertForbidden();
    }
}
