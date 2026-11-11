<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\ScopeValidator;
use App\Models\User;
use App\Support\ApprovalResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ScopeValidatorsTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function reviewer(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->grantAccess($user, 'entity_admin', 'university');

        return $user;
    }

    private function pendingRequest(?User $assigned): array
    {
        $member = User::factory()->create();
        $membership = Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'status' => 'pending', 'membership_type' => 'member', 'starts_at' => now()->toDateString()]);
        $approval = ApprovalRequest::query()->create([
            'request_type' => 'membership', 'requester_id' => $member->id, 'approvable_type' => Membership::class,
            'approvable_id' => $membership->id, 'assigned_reviewer_id' => $assigned?->id, 'status' => 'pending', 'submitted_at' => now(),
        ]);

        return [$membership, $approval, $member];
    }

    public function test_admin_designates_and_removes_validators_among_eligible_accounts_only(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $eligible = $this->reviewer('Valideuse Eligible');
        $plain = User::factory()->create(['name' => 'Simple Membre']);

        $page = $this->actingAs($admin)->get('/admin/structure/perimetres')->assertOk();
        $page->assertSee('Validateurs par entité')->assertSee('Valideuse Eligible')->assertDontSee('Simple Membre');

        $this->actingAs($admin)->post('/admin/structure/perimetres/university/validateurs', ['user_id' => $plain->id])->assertSessionHas('error');
        $this->assertSame(0, ScopeValidator::query()->count());

        $this->actingAs($admin)->post('/admin/structure/perimetres/university/validateurs', ['user_id' => $eligible->id])->assertSessionHas('status');
        $this->actingAs($admin)->post('/admin/structure/perimetres/university/validateurs', ['user_id' => $eligible->id]);
        $this->assertSame(1, ScopeValidator::query()->where('scope', 'university')->count());

        $this->actingAs($admin)->delete("/admin/structure/perimetres/university/validateurs/{$eligible->id}")->assertSessionHas('status');
        $this->assertSame(0, ScopeValidator::query()->count());
    }

    public function test_designated_validators_are_assigned_first_and_never_the_requester(): void
    {
        $first = $this->reviewer('Premier');
        $second = $this->reviewer('Second');
        ScopeValidator::query()->create(['scope' => 'university', 'user_id' => $second->id]);
        ScopeValidator::query()->create(['scope' => 'university', 'user_id' => $first->id]);

        $this->assertSame([$second->id, $first->id], ApprovalResolver::reviewers('university', 999999));
        $this->assertSame($second->id, ApprovalResolver::resolveMembershipReviewer('university', 999999));
        $this->assertSame([$first->id], ApprovalResolver::reviewers('university', $second->id));
    }

    public function test_without_designated_validators_the_automatic_choice_still_applies(): void
    {
        $auto = $this->reviewer('Automatique');

        $this->assertSame([$auto->id], ApprovalResolver::reviewers('university', 999999));
    }

    public function test_designated_validator_can_decide_a_request_assigned_to_someone_else(): void
    {
        $assigned = $this->reviewer('Assigne');
        $designated = $this->reviewer('Designe');
        $other = $this->reviewer('Autre');
        ScopeValidator::query()->create(['scope' => 'university', 'user_id' => $designated->id]);
        [$membership] = $this->pendingRequest($assigned);

        $this->actingAs($other)->patch("/admin/adhesions/{$membership->id}/decision", ['decision' => 'approved'])->assertSessionHas('error');
        $this->assertSame('pending', $membership->fresh()->status);

        $this->actingAs($designated)->get('/admin/adhesions')->assertOk()->assertSee('Accepter');
        $this->actingAs($designated)->patch("/admin/adhesions/{$membership->id}/decision", ['decision' => 'approved'])->assertSessionHas('status');
        $this->assertSame('approved', $membership->fresh()->status);
        $this->assertSame($designated->id, $membership->fresh()->approved_by);
    }

    public function test_awaiting_count_includes_requests_of_the_entities_one_validates(): void
    {
        $assigned = $this->reviewer('Assigne');
        $designated = $this->reviewer('Designe');
        $other = $this->reviewer('Autre');
        ScopeValidator::query()->create(['scope' => 'university', 'user_id' => $designated->id]);
        $this->pendingRequest($assigned);

        $this->assertSame(1, ApprovalRequest::query()->awaiting($designated)->count());
        $this->assertSame(1, ApprovalRequest::query()->awaiting($assigned)->count());
        $this->assertSame(0, ApprovalRequest::query()->awaiting($other)->count());
    }
}
