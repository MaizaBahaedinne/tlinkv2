<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MembershipApprovalTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_membership_submission_creates_approval_request(): void
    {
        $reviewer = User::factory()->create();
        $requester = User::factory()->create();
        \App\Models\Profile::query()->create(['user_id' => $requester->id, 'privacy_settings' => [
            'educations' => [['school' => 'ISI']],
            'professional_experiences' => [['company' => 'Acme', 'role' => 'Stagiaire']],
            'skill_keywords' => ['PHP'],
        ]]);

        $this->grantAccess($reviewer, 'entity_admin', 'university');

        $response = $this->actingAs($requester->fresh())->postJson('/api/v1/memberships', [
            'scope' => 'university',
            'membership_type' => 'member',
            'motivation_letter' => 'Je souhaite rejoindre la Fondation pour contribuer activement à ses projets et à ses équipes.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('memberships', [
            'user_id' => $requester->id,
            'scope' => 'university',
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
        $user = User::factory()->create();

        $this->grantAccess($user, 'global_admin');

        $membership = Membership::query()->create([
            'user_id' => $user->id,
            'scope' => 'high-school',
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
