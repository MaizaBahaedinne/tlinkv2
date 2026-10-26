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

    public function test_matrix_is_the_single_source_of_rights_and_derives_the_permissions(): void
    {
        $position = $this->position();

        $this->actingAs($this->admin())->put("/admin/structure/postes/{$position->id}/habilitations", [
            'modules' => ['members.memberships' => ['read', 'update'], 'events.events' => ['read']],
            'scope_all' => '0',
        ])->assertSessionHasNoErrors();

        $position->refresh();
        $this->assertSame('custom', $position->access_level);
        $this->assertEqualsCanonicalizing(['membership.manage', 'event.manage'], $position->permissions);
        $this->assertSame(['read', 'update'], $position->module_permissions['members.memberships']);

        $this->actingAs($this->admin())->put("/admin/structure/postes/{$position->id}/habilitations", [
            'modules' => ['members.memberships' => ['read']], 'scope_all' => '1',
        ])->assertSessionHasNoErrors();

        $position->refresh();
        $this->assertSame(['membership.manage', 'scope.all'], $position->permissions);
    }

    public function test_a_level_converts_to_the_equivalent_matrix(): void
    {
        $matrix = ModulePermissions::fromPermissions(\App\Support\AccessLevels::permissions('moderator'));

        $this->assertSame(['governance.moderation' => ['read', 'update', 'delete']], $matrix);
        $this->assertSame(['post.moderate'], ModulePermissions::derivePermissions($matrix));
    }

    public function test_perimeter_table_changes_the_perimeter_without_touching_the_matrix(): void
    {
        $position = $this->position();
        ModulePermissions::apply($position, ['members.memberships' => ['read', 'update']], false);

        $this->actingAs($this->admin())->post('/admin/structure/postes/droits', ['rows' => [$position->id => ['scope' => 'high-school']]])->assertSessionHasNoErrors();

        $position->refresh();
        $this->assertSame('high-school', $position->scope);
        $this->assertSame(['members.memberships' => ['read', 'update']], $position->module_permissions);
        $this->assertSame(['membership.manage'], $position->permissions);
    }

    public function test_the_sheet_no_longer_offers_a_second_rights_editor(): void
    {
        $position = $this->position();

        $this->actingAs($this->admin())->get("/admin/structure/postes/{$position->id}/modifier")
            ->assertOk()->assertSee('Habilitations par module')->assertDontSee('name="permissions[]"', false);
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
