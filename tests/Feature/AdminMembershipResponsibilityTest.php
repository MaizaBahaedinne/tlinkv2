<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\ApprovalResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AdminMembershipResponsibilityTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function club(string $title, string $scope = 'university'): OrgNode
    {
        return OrgNode::query()->create([
            'relation_type' => 'hierarchy',
            'kind' => 'club',
            'category' => 'university_club',
            'title' => $title,
            'scope' => $scope,
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    /** Poste situé à l'intérieur d'un club, occupé par l'utilisateur. */
    private function holdInClub(User $user, OrgNode $club, string $level = 'club_president'): void
    {
        $position = OrgNode::query()->create([
            'parent_id' => $club->id,
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => 'multiple',
            'access_level' => $level,
            'category' => 'direction',
            'title' => 'Poste '.$level,
            'sort_order' => 0,
        ]);

        $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], ['full_name' => $user->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);
    }

    private function member(string $name, string $scope, ?OrgNode $club = null, string $status = 'approved', ?string $startsAt = '2025-10-01', ?string $endsAt = null): Membership
    {
        $user = User::factory()->create(['name' => $name]);

        return Membership::query()->create([
            'user_id' => $user->id,
            'scope' => $scope,
            'node_id' => $club?->id,
            'status' => $status,
            'membership_type' => 'member',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    private function currentMandate(): Mandate
    {
        return Mandate::query()->create(['label' => 'Mandat 2025-2026', 'start_year' => 2025, 'end_year' => 2026, 'bureau_name' => 'Bureau 2025', 'status' => 'current']);
    }

    public function test_scope_manager_sees_the_whole_scope_but_not_other_scopes(): void
    {
        $manager = User::factory()->create();
        $this->grantAccess($manager, 'entity_admin', 'university');
        $club = $this->club('Club Robotique');

        $this->member('Scope Level', 'university');
        $this->member('In Club', 'university', $club);
        $this->member('Other Scope', 'alumni');

        $this->actingAs($manager->fresh())->get('/admin/adhesions?mandate=all')
            ->assertOk()
            ->assertSee('Scope Level')
            ->assertSee('In Club')
            ->assertDontSee('Other Scope');
    }

    public function test_club_leader_only_sees_and_changes_memberships_of_their_own_club(): void
    {
        $leader = User::factory()->create();
        $mine = $this->club('Club Robotique');
        $other = $this->club('Club Théâtre');
        $this->holdInClub($leader, $mine);

        $own = $this->member('Mine Member', 'university', $mine);
        $foreign = $this->member('Foreign Member', 'university', $other);
        $this->member('Scope Wide Member', 'university');

        $this->actingAs($leader->fresh())->get('/admin/adhesions?mandate=all')
            ->assertOk()
            ->assertSee('Mine Member')
            ->assertDontSee('Foreign Member')
            ->assertDontSee('Scope Wide Member');

        $this->actingAs($leader->fresh())->patch("/admin/adhesions/{$foreign->id}", ['status' => 'suspended'])->assertForbidden();
        $this->assertSame('approved', $foreign->fresh()->status);

        $this->actingAs($leader->fresh())->patch("/admin/adhesions/{$own->id}", ['status' => 'suspended'])->assertSessionHas('status');
        $this->assertSame('suspended', $own->fresh()->status);
    }

    public function test_platform_admin_sees_everything(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $club = $this->club('Club Robotique');

        $this->member('Alpha One', 'university', $club);
        $this->member('Beta Two', 'alumni');

        $this->actingAs($admin->fresh())->get('/admin/adhesions?mandate=all')
            ->assertOk()
            ->assertSee('Alpha One')
            ->assertSee('Beta Two');
    }

    public function test_memberships_are_filtered_by_mandate_with_current_one_by_default(): void
    {
        $manager = User::factory()->create();
        $this->grantAccess($manager, 'entity_admin', 'university');
        $this->currentMandate();
        Mandate::query()->create(['label' => 'Mandat 2023-2024', 'start_year' => 2023, 'end_year' => 2024, 'bureau_name' => 'Bureau 2023', 'status' => 'closed']);

        $this->member('Ongoing Old', 'university', null, 'approved', '2023-10-01');
        $this->member('Ended Old', 'university', null, 'approved', '2023-10-01', '2024-06-30');
        $this->member('Started Recent', 'university', null, 'approved', '2025-10-15');
        $this->member('Waiting Request', 'university', null, 'pending', null);

        $current = $this->actingAs($manager->fresh())->get('/admin/adhesions')->assertOk();
        $current->assertSee('Ongoing Old')->assertSee('Started Recent')->assertSee('Waiting Request')->assertDontSee('Ended Old');

        $oldId = Mandate::query()->where('label', 'Mandat 2023-2024')->value('id');
        $old = $this->actingAs($manager->fresh())->get("/admin/adhesions?mandate={$oldId}")->assertOk();
        $old->assertSee('Ongoing Old')->assertSee('Ended Old')->assertSee('Waiting Request')->assertDontSee('Started Recent');

        $this->actingAs($manager->fresh())->get('/admin/adhesions?mandate=all')->assertOk()->assertSee('Ended Old')->assertSee('Started Recent');
    }

    public function test_participations_are_limited_to_the_responsibility(): void
    {
        $leader = User::factory()->create();
        $mine = $this->club('Club Robotique');
        $other = $this->club('Club Théâtre');
        $this->holdInClub($leader, $mine);
        $mandate = $this->currentMandate();

        foreach ([[$mine, 'Part Mine'], [$other, 'Part Foreign']] as [$club, $name]) {
            MandateParticipation::query()->create([
                'user_id' => User::factory()->create(['name' => $name])->id,
                'mandate_id' => $mandate->id,
                'scope' => 'university',
                'node_id' => $club->id,
                'position_title' => 'Membre',
                'status' => 'approved',
            ]);
        }

        $this->actingAs($leader->fresh())->get('/admin/adhesions?mandate=all')
            ->assertOk()
            ->assertSee('Part Mine')
            ->assertDontSee('Part Foreign');
    }

    public function test_club_leader_cannot_decide_requests_of_another_club_and_gets_their_own(): void
    {
        $leader = User::factory()->create();
        $mine = $this->club('Club Robotique');
        $other = $this->club('Club Théâtre');
        $this->holdInClub($leader, $mine);
        $this->grantAccess(User::factory()->create(), 'entity_admin', 'university');

        $foreign = $this->member('Foreign Applicant', 'university', $other, 'pending', null);
        $approval = ApprovalRequest::query()->create([
            'request_type' => 'membership',
            'requester_id' => $foreign->user_id,
            'approvable_type' => Membership::class,
            'approvable_id' => $foreign->id,
            'status' => 'pending',
        ]);

        $this->actingAs($leader->fresh())->get('/admin/approbations')->assertOk()->assertDontSee('Foreign Applicant');
        $this->actingAs($leader->fresh())->post("/admin/approbations/{$approval->id}", ['decision' => 'approved'])->assertSessionHas('error');
        $this->assertSame('pending', $approval->fresh()->status);

        $requester = User::factory()->create();
        $this->assertSame($leader->id, ApprovalResolver::resolveMembershipReviewer('university', $requester->id, $mine->id));
    }
}
