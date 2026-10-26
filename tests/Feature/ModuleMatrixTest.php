<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Models\User;
use App\Support\ModulePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ModuleMatrixTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    private function position(): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => OrgNode::query()->where('system_key', 'university-office')->value('id'),
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => 'multiple',
            'access_level' => 'none',
            'category' => 'national_office',
            'title' => 'Modérateur contenu',
            'sort_order' => 0,
        ]);
    }

    public function test_matrix_is_saved_and_read_is_added_automatically(): void
    {
        $admin = $this->admin();
        $position = $this->position();

        $this->actingAs($admin)->get("/admin/structure/postes/{$position->id}/modifier")->assertOk()->assertSee('Habilitations par module');

        $this->actingAs($admin)->put("/admin/structure/postes/{$position->id}/habilitations", [
            'modules' => ['feed.posts' => ['update', 'create']],
        ])->assertRedirect("/admin/structure/postes/{$position->id}/modifier")->assertSessionHasNoErrors();

        $this->assertSame(['create', 'read', 'update'], $position->fresh()->module_permissions['feed.posts']);
    }

    public function test_unsupported_actions_and_unknown_modules_are_dropped(): void
    {
        $position = $this->position();

        $this->actingAs($this->admin())->put("/admin/structure/postes/{$position->id}/habilitations", [
            'modules' => [
                'governance.audit' => ['read', 'delete'],
                'inconnu.module' => ['read'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['governance.audit' => ['read']], $position->fresh()->module_permissions);
    }

    public function test_empty_matrix_is_stored_as_null(): void
    {
        $position = $this->position();
        $position->update(['module_permissions' => ['feed.posts' => ['read']]]);

        $this->actingAs($this->admin())->put("/admin/structure/postes/{$position->id}/habilitations", [])->assertSessionHasNoErrors();

        $this->assertNull($position->fresh()->module_permissions);
    }

    public function test_non_admin_cannot_save_a_matrix(): void
    {
        $position = $this->position();
        $this->admin();

        $this->actingAs(User::factory()->create())->put("/admin/structure/postes/{$position->id}/habilitations", ['modules' => ['feed.posts' => ['read']]])->assertForbidden();

        $this->assertNull($position->fresh()->module_permissions);
    }

    public function test_grants_follow_the_position_scope(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $position = $this->grantAccess($user, 'none', 'university');
        $position->update(['module_permissions' => ['feed.posts' => ['create', 'read']]]);

        $this->assertTrue(ModulePermissions::can($user, 'feed.posts', 'create'));
        $this->assertTrue(ModulePermissions::can($user, 'feed.posts', 'read'));
        $this->assertFalse(ModulePermissions::can($user, 'feed.posts', 'delete'));
        $this->assertFalse(ModulePermissions::can($user, 'feed.comments', 'read'));
    }

    public function test_position_without_scope_grants_nothing(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $position = $this->grantAccess($user, 'none', 'university');
        $position->update(['scope' => null, 'module_permissions' => ['feed.posts' => ['read']]]);

        $this->assertFalse(ModulePermissions::can($user, 'feed.posts', 'read'));
    }
}
