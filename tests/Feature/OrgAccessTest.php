<?php

namespace Tests\Feature;

use App\Models\Entity;
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

    private function entity(string $slug): Entity
    {
        return Entity::query()->create(['slug' => $slug, 'name' => ucfirst($slug), 'category' => 'test']);
    }

    public function test_user_without_position_has_no_rights(): void
    {
        $this->assertSame([], OrgAccess::grants(User::factory()->create()));
    }

    public function test_position_level_grants_its_permissions_in_its_entity_only(): void
    {
        $user = User::factory()->create();
        $entity = $this->entity('alpha');
        $this->grantAccess($user, 'manager', $entity);

        $grants = OrgAccess::grants($user);

        $this->assertEqualsCanonicalizing(AccessLevels::permissions('manager'), array_keys($grants));
        $this->assertSame([$entity->id], $grants['membership.manage']);
        $this->assertArrayNotHasKey('entity.manage', $grants);
    }

    public function test_global_admin_level_is_global(): void
    {
        $user = User::factory()->create();
        $this->grantAccess($user, 'global_admin');

        $this->assertSame([null], OrgAccess::grants($user)['entity.manage']);
    }

    public function test_entity_is_inherited_from_the_nearest_parent(): void
    {
        $user = User::factory()->create();
        $entity = $this->entity('beta');

        $parent = OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => 'direction',
            'title' => 'Bureau', 'entity_id' => $entity->id, 'sort_order' => 0,
        ]);
        $child = OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'single', 'access_level' => 'moderator',
            'category' => 'direction', 'title' => 'Modérateur', 'parent_id' => $parent->id, 'sort_order' => 0,
        ]);
        $this->placeOn($child, $user);

        $this->assertSame([$entity->id], OrgAccess::grants($user)['post.moderate']);
    }

    public function test_scoped_level_without_any_entity_grants_nothing(): void
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

    public function test_membership_reviewer_is_the_entity_admin_then_a_global_admin(): void
    {
        $entity = $this->entity('gamma');
        $requester = User::factory()->create();
        $global = User::factory()->create();
        $local = User::factory()->create();

        $this->grantAccess($global, 'global_admin');
        $this->assertSame($global->id, ApprovalResolver::resolveMembershipReviewer($entity->id, $requester->id));

        $this->grantAccess($local, 'entity_admin', $entity);
        $this->assertSame($local->id, ApprovalResolver::resolveMembershipReviewer($entity->id, $requester->id));

        $this->assertSame($global->id, ApprovalResolver::resolveMembershipReviewer($entity->id, $local->id));
    }
}
