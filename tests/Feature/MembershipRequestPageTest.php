<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MembershipRequestPageTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function club(string $title = 'Club Robotique', string $scope = 'university', string $status = 'active'): OrgNode
    {
        return OrgNode::query()->create([
            'relation_type' => 'hierarchy',
            'kind' => 'club',
            'category' => 'university_club',
            'title' => $title,
            'scope' => $scope,
            'status' => $status,
            'sort_order' => 0,
        ]);
    }

    public function test_page_requires_login_and_is_linked_from_the_user_menu(): void
    {
        $this->get('/adhesions')->assertRedirect('/connexion');

        $member = User::factory()->create();
        $this->club();

        $this->actingAs($member)->get('/adhesions')->assertOk()
            ->assertSee('Demander une adhésion')
            ->assertSee('Club Robotique')
            ->assertSee('Vous n’avez encore aucune adhésion');

        $this->actingAs($member)->get('/profil')->assertOk()->assertSee(route('memberships.index'), false);
    }

    public function test_member_requests_a_scope_membership_and_reviewer_is_notified(): void
    {
        $reviewer = User::factory()->create();
        $this->grantAccess($reviewer, 'entity_admin', 'university');
        $member = User::factory()->create();

        $this->actingAs($member)->post('/adhesions', ['target' => 'scope:university'])
            ->assertRedirect(route('memberships.index'))->assertSessionHas('status');

        $membership = Membership::query()->where('user_id', $member->id)->firstOrFail();
        $this->assertSame(['university', null, 'pending', 'member'], [$membership->scope, $membership->node_id, $membership->status, $membership->membership_type]);
        $this->assertSame($reviewer->id, ApprovalRequest::query()->where('approvable_id', $membership->id)->value('assigned_reviewer_id'));
        $this->assertSame(1, AppNotification::query()->where('user_id', $reviewer->id)->where('type', 'membership.submitted')->count());

        $this->actingAs($member)->get('/adhesions')->assertOk()->assertSee('En attente de validation');
    }

    public function test_member_requests_a_club_and_the_scope_comes_from_the_club(): void
    {
        $member = User::factory()->create();
        $club = $this->club();

        $this->actingAs($member)->post('/adhesions', ['target' => 'club:'.$club->id])->assertRedirect(route('memberships.index'));

        $membership = Membership::query()->where('user_id', $member->id)->firstOrFail();
        $this->assertSame([$club->id, 'university', 'pending'], [$membership->node_id, $membership->scope, $membership->status]);
    }

    public function test_duplicate_inactive_and_unknown_targets_are_refused(): void
    {
        $member = User::factory()->create();
        $club = $this->club();
        $closed = $this->club('Club fermé', 'university', 'inactive');

        $this->actingAs($member)->post('/adhesions', ['target' => 'scope:alumni'])->assertSessionHasNoErrors();
        $this->actingAs($member)->post('/adhesions', ['target' => 'scope:alumni'])->assertSessionHasErrors('target');
        $this->actingAs($member)->post('/adhesions', ['target' => 'club:'.$closed->id])->assertSessionHasErrors('target');
        $this->actingAs($member)->post('/adhesions', ['target' => 'club:99999'])->assertSessionHasErrors('target');
        $this->actingAs($member)->post('/adhesions', ['target' => 'scope:inconnu'])->assertSessionHasErrors('target');
        $this->actingAs($member)->post('/adhesions', [])->assertSessionHasErrors('target');

        $this->assertSame(1, Membership::query()->where('user_id', $member->id)->count());

        // Une adhésion refusée n'empêche pas de redemander.
        Membership::query()->where('user_id', $member->id)->update(['status' => 'rejected', 'rejection_reason' => 'Dossier incomplet']);
        $this->actingAs($member)->get('/adhesions')->assertSee('Refusée')->assertSee('Dossier incomplet');
        $this->actingAs($member)->post('/adhesions', ['target' => 'scope:alumni'])->assertSessionHasNoErrors();
        $this->assertSame(2, Membership::query()->where('user_id', $member->id)->count());
        $this->assertNotNull($club->id);
    }

    public function test_page_shows_positions_by_mandate_with_edit_link_for_admins_only(): void
    {
        $root = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'access_level' => 'none', 'category' => 'direction', 'title' => 'Bureau 2026', 'sort_order' => 0]);
        $post = OrgNode::query()->create(['parent_id' => $root->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'access_level' => 'none', 'category' => 'direction', 'title' => 'Trésorier', 'sort_order' => 0]);
        \App\Models\Mandate::query()->create(['label' => '2026-2027', 'start_year' => 2026, 'end_year' => 2027, 'bureau_name' => 'Bureau', 'is_current' => true, 'root_node_id' => $root->id]);

        $member = User::factory()->create();
        $person = \App\Models\OrgPerson::query()->create(['user_id' => $member->id, 'full_name' => $member->name, 'contact_visible' => true]);
        \App\Models\OrgAssignment::query()->create(['node_id' => $post->id, 'person_id' => $person->id]);

        $this->actingAs($member)->get('/adhesions')->assertOk()
            ->assertSee('Mandat 2026-2027')->assertSee('Trésorier')->assertDontSee('Modifier le poste');

        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $this->actingAs($admin->fresh())->get('/adhesions')->assertOk()->assertDontSee('Trésorier');

        $this->grantPlatformAdmin($member);
        $this->actingAs($member->fresh())->get('/adhesions')->assertSee('Modifier le poste');
    }

    public function test_page_only_lists_the_members_own_memberships(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        Membership::query()->create(['user_id' => $other->id, 'scope' => 'foundation', 'status' => 'approved', 'membership_type' => 'member']);

        $this->actingAs($member)->get('/adhesions')->assertOk()->assertSee('Vous n’avez encore aucune adhésion');
    }
}
