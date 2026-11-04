<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Models\User;
use App\Support\OrgAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class RoleSimulatorTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function globalAdmin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    /** Poste simulable : un responsable d'adhésions du périmètre université, occupé par un autre compte. */
    private function managerPosition(): OrgNode
    {
        return $this->grantAccess(User::factory()->create(), 'manager', 'university');
    }

    public function test_global_admin_acts_with_the_rights_of_the_simulated_position_only(): void
    {
        $admin = $this->globalAdmin();
        $position = $this->managerPosition();

        $this->actingAs($admin)->get('/admin/lancement')->assertOk();

        $this->post('/admin/simulateur/'.$position->id)
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('role_simulation.node_id', $position->id);

        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'admin.simulation.started', 'target_id' => $position->id]);

        // Droits du poste : adhésions oui, pilotage de l'organisation non.
        $this->get('/admin/utilisateurs')->assertOk();
        $this->get('/admin/lancement')->assertForbidden();
        $this->get('/admin/structure/postes')->assertForbidden();
        $this->get('/admin')->assertOk()->assertSee('Simulation de rôle')->assertSee($position->title);

        // Le vrai compte n'est pas modifié.
        $this->assertContains(null, OrgAccess::grants($admin)['entity.manage']);
    }

    public function test_stopping_the_simulation_restores_the_real_rights(): void
    {
        $admin = $this->globalAdmin();
        $position = $this->managerPosition();

        $this->actingAs($admin)->post('/admin/simulateur/'.$position->id);
        $this->get('/admin/lancement')->assertForbidden();

        $this->post('/simulation/quitter')->assertRedirect(route('admin.simulator.index'));

        $this->assertNull(session('role_simulation'));
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'admin.simulation.stopped', 'target_id' => $position->id]);
        $this->get('/admin/lancement')->assertOk();
    }

    public function test_stop_without_a_simulation_is_refused(): void
    {
        $this->actingAs($this->globalAdmin())->post('/simulation/quitter')->assertForbidden();
    }

    public function test_global_admin_and_positions_managers_cannot_be_simulated(): void
    {
        $admin = $this->globalAdmin();
        $other = User::factory()->create();

        $globalAdminNode = $this->grantAccess($other, 'global_admin');
        $unrestrictedEntityAdmin = $this->grantAccess($other, 'entity_admin', 'university');
        $positionsManager = $this->grantAccess($other, 'entity_admin', 'university');
        $positionsManager->update(['module_permissions' => ['organization.positions' => ['read', 'update']]]);
        $modulesManager = $this->grantAccess($other, 'entity_admin', 'university');
        $modulesManager->update(['module_permissions' => ['governance.modules' => ['read']]]);

        foreach ([$globalAdminNode, $unrestrictedEntityAdmin, $positionsManager, $modulesManager] as $node) {
            $this->actingAs($admin)->post('/admin/simulateur/'.$node->id)->assertForbidden();
        }

        $this->assertNull(session('role_simulation'));
    }

    public function test_entity_admin_restricted_away_from_positions_can_be_simulated(): void
    {
        $admin = $this->globalAdmin();
        $node = $this->grantAccess(User::factory()->create(), 'entity_admin', 'university');
        $node->update(['module_permissions' => ['members.users' => ['read']]]);

        $this->actingAs($admin)->post('/admin/simulateur/'.$node->id)->assertRedirect();
        $this->get('/admin/structure/postes')->assertForbidden();
        $this->get('/admin/modules')->assertForbidden();
    }

    public function test_only_global_admins_can_use_the_simulator(): void
    {
        $position = $this->managerPosition();
        $manager = User::factory()->create();
        $this->grantAccess($manager, 'manager', 'university');
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/simulateur')->assertForbidden();
        $this->actingAs($member)->post('/admin/simulateur/'.$position->id)->assertForbidden();
        $this->actingAs($manager)->get('/admin/simulateur')->assertForbidden();
        $this->actingAs($manager)->post('/admin/simulateur/'.$position->id)->assertForbidden();
    }

    public function test_a_simulation_cannot_be_nested_or_reach_the_simulator(): void
    {
        $admin = $this->globalAdmin();
        $position = $this->managerPosition();

        $this->actingAs($admin)->post('/admin/simulateur/'.$position->id);

        $this->get('/admin/simulateur')->assertForbidden();
        $this->post('/admin/simulateur/'.$position->id)->assertForbidden();
    }

    public function test_simulation_is_dropped_when_the_position_stops_being_simulable(): void
    {
        $admin = $this->globalAdmin();
        $position = $this->managerPosition();

        $this->actingAs($admin)->post('/admin/simulateur/'.$position->id);
        $position->update(['access_level' => 'global_admin', 'scope' => null]);

        $this->get('/admin/lancement')->assertOk();
        $this->assertNull(session('role_simulation'));
    }

    public function test_simulation_does_not_apply_to_another_account(): void
    {
        $admin = $this->globalAdmin();
        $position = $this->managerPosition();
        $this->actingAs($admin)->post('/admin/simulateur/'.$position->id);

        $other = User::factory()->create();
        $this->actingAs($other)->get('/admin/utilisateurs')->assertForbidden();

        $this->assertNull(session('role_simulation'));
    }

    public function test_index_lists_only_simulable_positions(): void
    {
        $admin = $this->globalAdmin();
        $other = User::factory()->create();
        $manager = $this->grantAccess($other, 'manager', 'university');
        $manager->update(['title' => 'Responsable simulable']);
        $this->grantAccess($other, 'global_admin')->update(['title' => 'Poste administrateur global']);

        $this->actingAs($admin)->get('/admin/simulateur')
            ->assertOk()
            ->assertSee('Responsable simulable')
            ->assertDontSee('Poste administrateur global');

        $this->get('/admin/simulateur?q=introuvable')->assertOk()->assertDontSee('Responsable simulable');
    }

    public function test_my_office_follows_the_simulated_position(): void
    {
        $admin = $this->globalAdmin();
        $position = $this->managerPosition();

        $this->assertNotContains($position->id, \App\Support\MyOffice::heldNodeIds($admin));

        $this->actingAs($admin)->post('/admin/simulateur/'.$position->id);
        \App\Support\RoleSimulator::activate($admin, $position);

        $this->assertSame([$position->id], \App\Support\MyOffice::heldNodeIds($admin));
    }

    public function test_menu_entry_is_shown_to_global_admins_only(): void
    {
        $admin = $this->globalAdmin();
        $entityAdmin = User::factory()->create();
        $node = $this->grantAccess($entityAdmin, 'entity_admin', 'university');
        $node->update(['module_permissions' => ['members.users' => ['read']]]);

        $this->actingAs($admin)->get('/admin')->assertSee('Simulateur de rôles');
        $this->actingAs($entityAdmin)->get('/admin')->assertDontSee('Simulateur de rôles');
    }
}
