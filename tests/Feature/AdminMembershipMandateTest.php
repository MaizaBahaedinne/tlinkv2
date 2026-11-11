<?php

namespace Tests\Feature;

use App\Models\Mandate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\PositionMemberships;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AdminMembershipMandateTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function mandates(): array
    {
        $old = Mandate::query()->create(['label' => '2020-2021', 'bureau_name' => 'Bureau 2020', 'start_year' => 2020, 'end_year' => 2021, 'status' => 'closed']);
        $current = Mandate::query()->create(['label' => '2026-2027', 'bureau_name' => 'Bureau 2026', 'start_year' => 2026, 'end_year' => 2027, 'status' => 'current']);

        return [$old, $current];
    }

    private function membership(User $user, string $start, string $status = 'approved', ?string $end = null): Membership
    {
        return Membership::query()->create(['user_id' => $user->id, 'scope' => 'university', 'status' => $status, 'membership_type' => 'member', 'starts_at' => $start, 'ends_at' => $end]);
    }

    public function test_current_mandate_filter_only_lists_memberships_of_that_mandate(): void
    {
        [$old, $current] = $this->mandates();
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $legacy = User::factory()->create(['name' => 'Ancien Membre']);
        $recent = User::factory()->create(['name' => 'Nouveau Membre']);
        $this->membership($legacy, '2020-10-01');
        $this->membership($recent, '2026-10-01');

        $this->actingAs($admin)->get('/admin/adhesions?mandate='.$current->id)->assertOk()
            ->assertSee('Nouveau Membre')->assertDontSee('Ancien Membre');

        $this->actingAs($admin)->get('/admin/adhesions?mandate='.$old->id)->assertOk()
            ->assertSee('Ancien Membre')->assertDontSee('Nouveau Membre');
    }

    public function test_position_holders_of_the_current_mandate_get_a_validated_membership(): void
    {
        [, $current] = $this->mandates();
        $club = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'club', 'capacity' => 'multiple', 'category' => 'direction', 'title' => 'AAC', 'scope' => 'university', 'sort_order' => 0]);
        $position = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'single', 'parent_id' => $club->id, 'category' => 'direction', 'title' => 'Trésorier', 'sort_order' => 0]);
        $holder = User::factory()->create();
        $person = OrgPerson::query()->create(['user_id' => $holder->id, 'full_name' => $holder->name]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $membership = Membership::query()->where('user_id', $holder->id)->first();

        $this->assertNotNull($membership);
        $this->assertSame('approved', $membership->status);
        $this->assertSame('university', $membership->scope);
        $this->assertSame($club->id, $membership->node_id);
        $this->assertSame($position->id, $membership->position_id);
        $this->assertSame('Trésorier', $membership->membership_type);
        $this->assertSame($current->windowStart()->toDateString(), $membership->starts_at->toDateString());

        // Idempotent : pas de doublon, et un membre déjà adhérent du mandat n'en reçoit pas une seconde.
        $this->assertSame(0, PositionMemberships::sync());
        $this->assertSame(1, Membership::query()->where('user_id', $holder->id)->count());
    }

    public function test_position_holder_keeps_his_existing_membership_of_the_mandate(): void
    {
        $this->mandates();
        $user = User::factory()->create();
        $this->membership($user, '2026-10-05');
        $this->grantAccess($user, 'manager', 'university');

        $this->assertSame(1, Membership::query()->where('user_id', $user->id)->count());
    }

    public function test_migration_gives_existing_position_memberships_the_position_title_as_role(): void
    {
        $position = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'single', 'category' => 'direction', 'title' => 'Présidente', 'scope' => 'university', 'sort_order' => 0]);
        $membership = $this->membership(User::factory()->create(), '2026-10-01');
        $membership->forceFill(['position_id' => $position->id])->save();
        $plain = $this->membership(User::factory()->create(), '2026-10-01');

        (require base_path('database/migrations/2026_12_14_120000_set_position_title_as_membership_role.php'))->up();

        $this->assertSame('Présidente', $membership->fresh()->membership_type);
        $this->assertSame('member', $plain->fresh()->membership_type);
    }

    public function test_pending_membership_is_accepted_or_refused_directly_from_the_list(): void
    {
        $this->mandates();
        $manager = User::factory()->create();
        $this->grantAccess($manager, 'entity_admin', 'university');
        $accepted = $this->membership(User::factory()->create(), '2026-10-01', 'pending');
        $refused = $this->membership(User::factory()->create(), '2026-10-01', 'pending');

        $this->actingAs($manager)->get('/admin/adhesions')->assertOk()->assertSee('Accepter')->assertSee('Refuser');

        $this->actingAs($manager)->patch("/admin/adhesions/{$accepted->id}/decision", ['decision' => 'approved'])->assertSessionHas('status');
        $this->actingAs($manager)->patch("/admin/adhesions/{$refused->id}/decision", ['decision' => 'rejected', 'explanation' => 'Dossier incomplet'])->assertSessionHas('status');

        $this->assertSame('approved', $accepted->fresh()->status);
        $this->assertSame($manager->id, $accepted->fresh()->approved_by);
        $this->assertSame('rejected', $refused->fresh()->status);
        $this->assertSame('Dossier incomplet', $refused->fresh()->rejection_reason);

        // Déjà traitée : aucune seconde décision.
        $this->actingAs($manager)->patch("/admin/adhesions/{$accepted->id}/decision", ['decision' => 'rejected'])->assertSessionHas('error');
        $this->assertSame('approved', $accepted->fresh()->status);
    }

    public function test_a_plain_member_cannot_decide_a_membership(): void
    {
        $this->mandates();
        $pending = $this->membership(User::factory()->create(), '2026-10-01', 'pending');

        $this->actingAs(User::factory()->create())->patch("/admin/adhesions/{$pending->id}/decision", ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $pending->fresh()->status);
    }
}
