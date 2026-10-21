<?php

namespace Tests\Feature;

use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\AccessLevels;
use App\Support\ApprovalResolver;
use App\Support\OrgAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class OrgAccessTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_user_without_position_has_no_rights(): void
    {
        $this->assertSame([], OrgAccess::grants(User::factory()->create()));
    }

    public function test_position_level_grants_its_permissions_in_its_scope_only(): void
    {
        $user = User::factory()->create();
        $this->grantAccess($user, 'manager', 'alumni');

        $grants = OrgAccess::grants($user);

        $this->assertEqualsCanonicalizing(AccessLevels::permissions('manager'), array_keys($grants));
        $this->assertSame(['alumni'], $grants['membership.manage']);
        $this->assertArrayNotHasKey('entity.manage', $grants);
    }

    public function test_global_admin_level_is_global(): void
    {
        $user = User::factory()->create();
        $this->grantAccess($user, 'global_admin');

        $this->assertSame([null], OrgAccess::grants($user)['entity.manage']);
    }

    public function test_scope_is_inherited_from_the_nearest_parent(): void
    {
        $user = User::factory()->create();

        $parent = OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => 'direction',
            'title' => 'Bureau', 'scope' => 'high-school', 'sort_order' => 0,
        ]);
        $child = OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'single', 'access_level' => 'moderator',
            'category' => 'direction', 'title' => 'Modérateur', 'parent_id' => $parent->id, 'sort_order' => 0,
        ]);
        $this->placeOn($child, $user);

        $this->assertSame(['high-school'], OrgAccess::grants($user)['post.moderate']);
    }

    public function test_scoped_level_without_any_scope_grants_nothing(): void
    {
        $user = User::factory()->create();
        $node = OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'single', 'access_level' => 'entity_admin',
            'category' => 'direction', 'title' => 'Sans entité', 'sort_order' => 0,
        ]);
        $this->placeOn($node, $user);

        $this->assertSame([], OrgAccess::grants($user));
    }

    public function test_ended_assignment_loses_its_rights(): void
    {
        $user = User::factory()->create();
        $node = $this->grantAccess($user, 'global_admin');
        $this->assertNotSame([], OrgAccess::grants($user));

        OrgAssignment::query()->where('node_id', $node->id)->update(['ends_at' => now()->subDay()->toDateString()]);

        $this->assertSame([], OrgAccess::grants($user));
    }

    public function test_access_level_is_ignored_on_non_position_nodes(): void
    {
        $user = User::factory()->create();
        $node = OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'unit', 'access_level' => 'global_admin',
            'category' => 'direction', 'title' => 'Unité', 'sort_order' => 0,
        ]);
        $this->placeOn($node, $user);

        $this->assertSame([], OrgAccess::grants($user));
    }

    public function test_global_admin_cannot_remove_their_own_access(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $node = OrgNode::query()->where('system_key', 'national-administrators')->firstOrFail();

        $this->actingAs($admin)
            ->putJson("/organigramme/blocs/{$node->id}/affectations", ['person_ids' => []])
            ->assertUnprocessable();

        $person = OrgPerson::query()->where('user_id', $admin->id)->firstOrFail();
        $this->assertDatabaseHas('org_assignments', ['node_id' => $node->id, 'person_id' => $person->id]);
    }

    public function test_membership_reviewer_is_the_scope_admin_then_a_global_admin(): void
    {
        $requester = User::factory()->create();
        $global = User::factory()->create();
        $local = User::factory()->create();

        $this->grantAccess($global, 'global_admin');
        $this->assertSame($global->id, ApprovalResolver::resolveMembershipReviewer('alumni', $requester->id));

        $this->grantAccess($local, 'entity_admin', 'alumni');
        $this->assertSame($local->id, ApprovalResolver::resolveMembershipReviewer('alumni', $requester->id));

        $this->assertSame($global->id, ApprovalResolver::resolveMembershipReviewer('alumni', $local->id));
    }
}
