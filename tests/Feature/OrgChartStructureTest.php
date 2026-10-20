<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\EntitySeeder;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Types de blocs (poste, unite, departement de bureau national, club, departement de club)
 * et capacite des postes (titulaire unique ou plusieurs personnes).
 */
class OrgChartStructureTest extends TestCase
{
    use RefreshDatabase;

    private ?User $editorUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([GovernorateSeeder::class, EntitySeeder::class]);
    }

    private function editor(): User
    {
        if ($this->editorUser) {
            return $this->editorUser;
        }

        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'Admin global', 'slug' => 'admin-'.uniqid(), 'scope_type' => 'global']);
        $permission = Permission::query()->firstOrCreate(['slug' => 'entity.manage'], ['name' => 'entity.manage']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'status' => 'active', 'starts_at' => now()->toDateString()]);

        return $this->editorUser = $user;
    }

    /** @return array<string, mixed> */
    private function chart(): array
    {
        return $this->actingAs($this->editor())->getJson('/organigramme/donnees')->assertOk()->json();
    }

    private function key(string $systemKey): OrgNode
    {
        $this->chart();

        return OrgNode::query()->where('system_key', $systemKey)->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function createBlock(OrgNode $parent, string $kind, array $overrides = [])
    {
        return $this->actingAs($this->editor())->postJson('/organigramme/blocs', $overrides + [
            'title' => 'Bloc '.$kind,
            'kind' => $kind,
            'relation_type' => 'hierarchy',
            'parent_id' => $parent->id,
            'validation_status' => 'confirmed',
            'children_layout' => 'horizontal',
            'is_collapsed' => 0,
        ]);
    }

    private function assign(OrgNode $node, User $user)
    {
        return $this->actingAs($this->editor())->postJson('/organigramme/blocs/'.$node->id.'/utilisateurs', ['user_id' => $user->id]);
    }

    public function test_a_single_position_refuses_a_second_person_but_a_multiple_one_accepts_it(): void
    {
        $director = $this->key('executive-director');
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->assertSame('single', $director->capacity);
        $this->assign($director, $alice)->assertOk();
        $this->assign($director, $alice)->assertOk();
        $this->assign($director, $bob)->assertUnprocessable()->assertJsonValidationErrors('person_ids');
        $this->assertSame(1, $director->assignments()->count());

        $this->actingAs($this->editor())->patchJson('/organigramme/blocs/'.$director->id, [
            'title' => $director->title,
            'relation_type' => 'hierarchy',
            'parent_id' => $director->parent_id,
            'validation_status' => 'confirmed',
            'children_layout' => 'horizontal',
            'is_collapsed' => 0,
            'capacity' => 'multiple',
        ])->assertOk();

        $this->assign($director->fresh(), $bob)->assertOk();
        $this->assertSame(2, $director->assignments()->count());

        // Repasser en titulaire unique avec deux personnes en place est refuse.
        $this->actingAs($this->editor())->patchJson('/organigramme/blocs/'.$director->id, [
            'title' => $director->title,
            'relation_type' => 'hierarchy',
            'parent_id' => $director->parent_id,
            'validation_status' => 'confirmed',
            'children_layout' => 'horizontal',
            'is_collapsed' => 0,
            'capacity' => 'single',
        ])->assertUnprocessable();

        $this->assertSame('multiple', $director->fresh()->capacity);
    }

    public function test_only_positions_can_hold_people(): void
    {
        $unit = $this->key('regional-clubs');
        $department = $this->key('university-dept-marketing');
        $user = User::factory()->create();

        $this->assertSame('unit', $unit->kind);
        $this->assertSame('department', $department->kind);
        $this->assign($unit, $user)->assertUnprocessable();
        $this->assign($department, $user)->assertUnprocessable();
        $this->assertSame(0, $unit->assignments()->count());
    }

    public function test_national_offices_are_organised_in_departments(): void
    {
        $department = $this->key('university-dept-hr');
        $manager = $this->key('university-manager-hr');
        $assistant = $this->key('university-assistant-hr');

        $this->assertSame($this->key('university-office')->id, $department->parent_id);
        $this->assertSame($department->id, $manager->parent_id);
        $this->assertSame('single', $manager->capacity);
        $this->assertSame('multiple', $assistant->capacity);
    }

    public function test_department_kinds_follow_their_location(): void
    {
        $office = $this->key('university-office');
        $club = OrgNode::query()->where('system_key', 'regional-club:tunis')->firstOrFail();
        $president = OrgNode::query()->where('parent_id', $club->id)->where('kind', 'position')->firstOrFail();

        $this->createBlock($office, 'department', ['title' => 'Département Partenariats'])->assertCreated();
        $this->createBlock($club, 'club_department', ['title' => 'Département Digital'])->assertCreated();
        $this->createBlock($president, 'club_department', ['title' => 'Département Sponsoring'])->assertCreated();

        // Un departement de club n'existe que dans un club, un departement de bureau national jamais dans un club.
        $this->createBlock($office, 'club_department')->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->createBlock($club, 'department')->assertUnprocessable()->assertJsonValidationErrors('kind');
    }

    public function test_club_template_groups_vice_presidents_in_club_departments(): void
    {
        $this->chart();
        $club = OrgNode::query()->where('system_key', 'regional-club:sfax')->firstOrFail();

        $departments = OrgNode::query()->where('kind', 'club_department')->whereIn('parent_id', $club->children()->pluck('id'))->get();
        $this->assertCount(4, $departments);

        $vp = OrgNode::query()->where('parent_id', $departments->first()->id)->firstOrFail();
        $this->assertSame('position', $vp->kind);
        $this->assertSame('single', $vp->capacity);
        $this->assertSame('multiple', OrgNode::query()->where('title', 'Assistants et membres')->firstOrFail()->capacity);
    }

    public function test_old_flat_structures_are_converted_once(): void
    {
        $this->chart();

        // Reproduit l'ancienne structure : manager directement sous le coordinateur.
        $office = $this->key('highschool-office');
        $department = $this->key('highschool-dept-events');
        $manager = $this->key('highschool-manager-events');
        $manager->update(['parent_id' => $office->id]);
        $department->delete();

        $installer = app(OrgChartInstaller::class);
        $this->assertGreaterThanOrEqual(1, $installer->restructure());
        $this->assertSame($office->id, $this->key('highschool-dept-events')->parent_id);
        $this->assertSame($this->key('highschool-dept-events')->id, $manager->fresh()->parent_id);

        // Idempotent.
        $this->assertSame(0, $installer->restructure());
    }

    public function test_exports_keep_the_capacity(): void
    {
        $this->chart();
        $payload = $this->actingAs($this->editor())->getJson('/organigramme/export.json')->assertOk()->json();

        $this->assertSame('multiple', collect($payload['nodes'])->firstWhere('system_key', 'university-assistant-hr')['capacity']);
        $this->assertContains('club_department', collect($payload['nodes'])->pluck('kind')->all());
    }
}
