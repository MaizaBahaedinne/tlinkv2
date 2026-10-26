<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class EntityMembersTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function member(string $scope, string $status = 'approved', string $name = 'Membre'): Membership
    {
        return Membership::query()->create([
            'user_id' => User::factory()->create(['name' => $name])->id,
            'scope' => $scope,
            'status' => $status,
            'membership_type' => 'member',
        ]);
    }

    private function manager(string $scope = 'university'): User
    {
        $manager = User::factory()->create();
        $this->grantAccess($manager, 'manager', $scope);

        return $manager;
    }

    public function test_manager_only_sees_members_of_their_own_entity_without_sub_entities(): void
    {
        $this->member('university', 'approved', 'Alice Université');
        $this->member('high-school', 'approved', 'Bob Lycée');
        $this->member('foundation', 'approved', 'Carla Fondation');

        $this->actingAs($this->manager('foundation'))->get('/admin/membres')
            ->assertOk()->assertSee('Carla Fondation')->assertDontSee('Alice Université')->assertDontSee('Bob Lycée');

        $this->actingAs($this->manager('university'))->get('/admin/membres')
            ->assertOk()->assertSee('Alice Université')->assertDontSee('Carla Fondation')->assertDontSee('Bob Lycée');
    }

    public function test_pending_and_rejected_requests_are_not_listed(): void
    {
        $this->member('university', 'pending', 'Demande En Attente');
        $this->member('university', 'rejected', 'Demande Rejetée');

        $this->actingAs($this->manager())->get('/admin/membres')->assertOk()->assertDontSee('Demande En Attente')->assertDontSee('Demande Rejetée');
    }

    public function test_manager_changes_status_only_inside_their_entity(): void
    {
        $manager = $this->manager();
        $own = $this->member('university');
        $foreign = $this->member('high-school');

        $this->actingAs($manager)->patch("/admin/membres/{$own->id}", ['status' => 'suspended'])->assertSessionHas('status');
        $this->assertSame('suspended', $own->fresh()->status);

        $this->actingAs($manager)->patch("/admin/membres/{$foreign->id}", ['status' => 'suspended'])->assertForbidden();
        $this->assertSame('approved', $foreign->fresh()->status);
    }

    public function test_manager_adds_an_existing_user_to_their_entity_only(): void
    {
        $manager = $this->manager();
        $user = User::factory()->create();

        $this->actingAs($manager)->post('/admin/membres', ['user_id' => $user->id, 'scope' => 'high-school'])->assertSessionHasErrors('scope');
        $this->assertSame(0, Membership::query()->where('user_id', $user->id)->count());

        $this->actingAs($manager)->post('/admin/membres', ['user_id' => $user->id, 'scope' => 'university'])->assertSessionHas('status');
        $this->assertDatabaseHas('memberships', ['user_id' => $user->id, 'scope' => 'university', 'status' => 'approved']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.member.added', 'actor_id' => $manager->id]);

        // Deja membre : pas de doublon.
        $this->actingAs($manager)->post('/admin/membres', ['user_id' => $user->id, 'scope' => 'university'])->assertSessionHas('error');
        $this->assertSame(1, Membership::query()->where('user_id', $user->id)->count());
    }

    public function test_archived_member_is_reactivated_instead_of_duplicated(): void
    {
        $archived = $this->member('university', 'archived');

        $this->actingAs($this->manager())->post('/admin/membres', ['user_id' => $archived->user_id, 'scope' => 'university'])->assertSessionHas('status');

        $this->assertSame('approved', $archived->fresh()->status);
        $this->assertSame(1, Membership::query()->where('user_id', $archived->user_id)->count());
    }

    public function test_global_admin_sees_every_entity_and_others_without_the_right_are_refused(): void
    {
        $this->member('university', 'approved', 'Alice Université');
        $this->member('high-school', 'approved', 'Bob Lycée');

        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $this->actingAs($admin)->get('/admin/membres')->assertOk()->assertSee('Alice Université')->assertSee('Bob Lycée');

        $moderator = User::factory()->create();
        $this->grantAccess($moderator, 'moderator', 'university');
        $this->actingAs($moderator)->get('/admin/membres')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/membres')->assertForbidden();
    }
}
