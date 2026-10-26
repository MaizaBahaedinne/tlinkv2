<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MenuMatrixSyncTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function manager(?array $matrix): User
    {
        $user = User::factory()->create();
        $this->grantAccess($user, 'manager', 'university')->update(['module_permissions' => $matrix]);

        return $user;
    }

    public function test_position_without_matrix_keeps_every_section_of_its_level(): void
    {
        $this->actingAs($this->manager(null))->get('/admin')->assertOk()
            ->assertSee('/admin/adhesions', false)->assertSee('/admin/evenements', false)->assertSee('/admin/approbations', false);
    }

    public function test_menu_only_lists_the_modules_ticked_in_the_matrix(): void
    {
        $manager = $this->manager(['members.memberships' => ['read']]);

        $this->actingAs($manager)->get('/admin')->assertOk()
            ->assertSee('/admin/adhesions', false)
            ->assertDontSee('/admin/evenements', false)
            ->assertDontSee('/admin/approbations', false)
            ->assertDontSee('/admin/moderation', false);
    }

    public function test_routes_follow_the_menu_and_the_ticked_actions(): void
    {
        $manager = $this->manager(['members.memberships' => ['read']]);
        $membership = Membership::query()->create(['user_id' => User::factory()->create()->id, 'scope' => 'university', 'status' => 'approved', 'membership_type' => 'member']);

        $this->actingAs($manager)->get('/admin/adhesions')->assertOk();
        $this->actingAs($manager)->get('/admin/evenements')->assertForbidden();
        $this->actingAs($manager)->patch("/admin/adhesions/{$membership->id}", ['status' => 'suspended'])->assertForbidden();
        $this->assertSame('approved', $membership->fresh()->status);

        OrgNode::query()->where('title', 'Poste de test manager')->update(['module_permissions' => json_encode(['members.memberships' => ['read', 'update']])]);

        $this->actingAs($manager)->patch("/admin/adhesions/{$membership->id}", ['status' => 'suspended'])->assertSessionHas('status');
    }

    public function test_matrix_never_grants_more_than_the_access_level(): void
    {
        $moderator = User::factory()->create();
        $this->grantAccess($moderator, 'moderator', 'university')->update(['module_permissions' => ['members.memberships' => ['read', 'update']]]);

        $this->actingAs($moderator)->get('/admin/adhesions')->assertForbidden();
    }

    public function test_a_matrix_cannot_lock_its_author_out_of_position_management(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $position = OrgNode::query()->where('system_key', 'national-administrators')->firstOrFail();

        $this->actingAs($admin)->put("/admin/structure/postes/{$position->id}/habilitations", ['modules' => ['feed.posts' => ['read']]])
            ->assertSessionHasErrors();

        $this->assertNull($position->fresh()->module_permissions);
    }
}
