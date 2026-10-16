<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_submission_creates_approval_request(): void
    {
        $entity = Entity::query()->create([
            'name' => 'University',
            'slug' => 'university',
            'category' => 'education',
        ]);

        $reviewer = User::factory()->create();
        $requester = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'Entity Administrator',
            'slug' => 'entity-administrator',
            'scope_type' => 'entity',
        ]);

        RoleAssignment::query()->create([
            'user_id' => $reviewer->id,
            'role_id' => $role->id,
            'entity_id' => $entity->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($requester)->postJson('/api/v1/memberships', [
            'entity_id' => $entity->id,
            'membership_type' => 'member',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('memberships', [
            'user_id' => $requester->id,
            'entity_id' => $entity->id,
            'status' => 'pending',
        ]);

        $membership = Membership::query()->where('user_id', $requester->id)->firstOrFail();

        $this->assertDatabaseHas('approval_requests', [
            'request_type' => 'membership',
            'requester_id' => $requester->id,
            'approvable_type' => Membership::class,
            'approvable_id' => $membership->id,
            'assigned_reviewer_id' => $reviewer->id,
            'status' => 'pending',
        ]);
    }

    public function test_requester_cannot_self_approve_membership(): void
    {
        $entity = Entity::query()->create([
            'name' => 'High School',
            'slug' => 'high-school',
            'category' => 'education',
        ]);

        $user = User::factory()->create();

        $permission = Permission::query()->create([
            'name' => 'Approve Requests',
            'slug' => 'approval.review',
        ]);

        $role = Role::query()->create([
            'name' => 'National Bureau Administrator',
            'slug' => 'national-bureau-administrator',
            'scope_type' => 'global',
        ]);

        $role->permissions()->attach($permission->id);

        RoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        $membership = Membership::query()->create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'status' => 'pending',
            'membership_type' => 'member',
        ]);

        $approval = ApprovalRequest::query()->create([
            'request_type' => 'membership',
            'requester_id' => $user->id,
            'approvable_type' => Membership::class,
            'approvable_id' => $membership->id,
            'assigned_reviewer_id' => $user->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/approvals/{$approval->id}/decision", [
            'decision' => 'approved',
            'explanation' => 'Test',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }
}
