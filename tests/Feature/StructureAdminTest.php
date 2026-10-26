<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class StructureAdminTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->seed(GovernorateSeeder::class);
        $this->grantPlatformAdmin($user);

        return $user;
    }

    private function universityClub(string $name = 'ESPRIT', bool $template = true): OrgNode
    {
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();

        return app(OrgChartInstaller::class)->createClub($branch, $name, 'university_club', Governorate::query()->first(), $template);
    }

    private function position(string $title, ?OrgNode $parent = null, string $level = 'none', string $capacity = 'single'): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => $parent?->id ?? OrgNode::query()->where('system_key', 'university-office')->value('id'),
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => $capacity,
            'access_level' => $level,
            'category' => 'national_office',
            'title' => $title,
            'sort_order' => 0,
        ]);
    }

    public function test_guests_and_non_admins_cannot_open_the_structure_screens(): void
    {
        $this->get('/admin/structure/postes')->assertRedirect('/connexion');

        $this->actingAs(User::factory()->create())->get('/admin/structure/postes')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/structure/clubs')->assertForbidden();
    }

    public function test_university_club_team_is_president_plus_four_departments_with_vp_assistant_and_members(): void
    {
        $this->admin();
        $club = $this->universityClub();

        $team = OrgNode::query()->where('subtitle', $club->title)->get();

        $this->assertSame(1, $team->where('title', 'Président de club')->count());
        $this->assertSame(4, $team->where('kind', 'club_department')->count());

        foreach (['Marketing', 'RH', 'Events', 'R&D'] as $label) {
            $department = $team->firstWhere('title', 'Département '.$label);
            $this->assertNotNull($department);

            $children = $team->where('parent_id', $department->id);
            $this->assertSame('single', $children->firstWhere('title', 'VP '.$label)?->capacity);
            $this->assertSame('single', $children->firstWhere('title', 'Assistant '.$label)?->capacity);
            $this->assertSame('multiple', $children->firstWhere('title', 'Membres '.$label)?->capacity);
        }

        $this->assertSame('club_president', $team->firstWhere('title', 'Président de club')->access_level);
        $this->assertNull($team->firstWhere('title', 'Autres membres et responsables'));
    }

    public function test_existing_clubs_are_aligned_with_the_template_and_it_is_idempotent(): void
    {
        $this->admin();
        $club = $this->universityClub();
        $team = fn () => OrgNode::query()->where('subtitle', $club->title);

        // Ancien modele : assistant sous le VP, bloc fourre-tout, pas de « Membres ».
        $department = $team()->where('title', 'Département Marketing')->firstOrFail();
        $vp = $team()->where('title', 'VP Marketing')->firstOrFail();
        $team()->where('title', 'Assistant Marketing')->delete();
        $team()->where('title', 'Membres Marketing')->delete();
        $assistant = OrgNode::query()->create([
            'parent_id' => $vp->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple',
            'category' => 'university_club', 'title' => 'Assistant Marketing', 'subtitle' => $club->title, 'sort_order' => 0,
        ]);
        $president = $team()->where('kind', 'position')->where('title', 'like', 'Président%')->firstOrFail();
        OrgNode::query()->create([
            'parent_id' => $president->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple',
            'category' => 'university_club', 'title' => 'Autres membres et responsables', 'subtitle' => $club->title, 'sort_order' => 9,
        ]);

        $installer = app(OrgChartInstaller::class);

        $this->assertGreaterThan(0, $installer->alignClubTeams());
        $this->assertSame($department->id, $assistant->fresh()->parent_id);
        $this->assertTrue($team()->where('title', 'Membres Marketing')->where('parent_id', $department->id)->exists());
        $this->assertFalse($team()->where('title', 'Autres membres et responsables')->exists());

        $this->assertSame(0, $installer->alignClubTeams());
    }

    public function test_positions_table_lists_levels_perimeters_and_holders_with_filters(): void
    {
        $admin = $this->admin();
        $manager = $this->position('Manager Test Alpha', null, 'manager');
        $holder = User::factory()->create(['name' => 'Salma Ben Ali']);
        $person = OrgPerson::query()->create(['user_id' => $holder->id, 'full_name' => $holder->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $manager->id, 'person_id' => $person->id]);
        $this->position('Poste Vacant Beta');

        $this->actingAs($admin)->get('/admin/structure/postes?q=Alpha')
            ->assertOk()
            ->assertSee('Manager Test Alpha')
            ->assertSee('Salma Ben Ali')
            ->assertSee('Hérité')
            ->assertDontSee('Poste Vacant Beta');

        $this->actingAs($admin)->get('/admin/structure/postes?holder=vacant&q=Beta')->assertSee('Poste Vacant Beta')->assertSee('Vacant');
        $this->actingAs($admin)->get('/admin/structure/postes?q=Salma')->assertSee('Manager Test Alpha');
        $this->actingAs($admin)->get('/admin/structure/postes?permission=membership.manage&scope=university&q=Test')->assertSee('Manager Test Alpha');
        $this->actingAs($admin)->get('/admin/structure/postes?permission=entity.manage&scope=university&q=Test')->assertDontSee('Manager Test Alpha');
    }

    public function test_global_admin_edits_a_position_role_perimeter_and_capacity(): void
    {
        $admin = $this->admin();
        $position = $this->position('VP à régler');

        $this->actingAs($admin)->get("/admin/structure/postes/{$position->id}/modifier")->assertOk()->assertSee('VP à régler');

        $this->actingAs($admin)->put("/admin/structure/postes/{$position->id}", [
            'title' => 'VP Events',
            'kind' => 'position',
            'parent_id' => $position->parent_id,
            'capacity' => 'multiple',
            'permissions' => ['', 'post.moderate'],
            'scope' => 'high-school',
        ])->assertRedirect("/admin/structure/postes/{$position->id}/modifier");

        $position->refresh();
        $this->assertSame('VP Events', $position->title);
        $this->assertSame('custom', $position->access_level);
        $this->assertSame(['post.moderate'], $position->permissions);
        $this->assertSame('high-school', $position->scope);
        $this->assertSame('multiple', $position->capacity);
    }

    public function test_scoped_admin_can_read_but_not_write(): void
    {
        $scoped = User::factory()->create();
        $this->seed(GovernorateSeeder::class);
        $this->grantPlatformAdmin(User::factory()->create());
        $this->grantAccess($scoped, 'entity_admin', 'university');
        $position = $this->position('Poste lisible');

        $this->actingAs($scoped)->get('/admin/structure/postes')->assertOk()->assertSee('Poste lisible');
        $this->actingAs($scoped)->get("/admin/structure/postes/{$position->id}/modifier")->assertOk();
        $this->actingAs($scoped)->put("/admin/structure/postes/{$position->id}", ['title' => 'Piraté', 'permissions' => ['entity.manage'], 'scope_all' => 1])->assertForbidden();
        $this->actingAs($scoped)->post('/admin/structure/postes/droits', ['rows' => [$position->id => ['permissions' => ['entity.manage'], 'scope_all' => 1]]])->assertForbidden();

        $this->assertSame('Poste lisible', $position->fresh()->title);
    }

    public function test_permissions_and_perimeter_are_set_position_by_position_and_never_remove_own_admin_rights(): void
    {
        $admin = $this->admin();
        $a = $this->position('Poste A');
        $b = $this->position('Poste B');

        $this->actingAs($admin)->post('/admin/structure/postes/droits', ['rows' => [
            $a->id => ['permissions' => ['membership.manage', 'event.manage'], 'scope' => 'high-school'],
            $b->id => ['permissions' => ['post.moderate'], 'scope_all' => 1, 'scope' => ''],
        ]])->assertRedirect()->assertSessionHas('status');

        $a->refresh();
        $b->refresh();

        $this->assertSame('custom', $a->access_level);
        $this->assertEqualsCanonicalizing(['membership.manage', 'event.manage'], $a->permissions);
        $this->assertSame('high-school', $a->scope);
        $this->assertContains('scope.all', $b->permissions);
        $this->assertNull($b->scope);

        // Un poste dont les droits ne changent pas garde son niveau prédéfini.
        $manager = $this->position('Poste Manager', null, 'manager');
        $this->actingAs($admin)->post('/admin/structure/postes/droits', ['rows' => [
            $manager->id => ['permissions' => \App\Support\AccessLevels::permissions('manager'), 'scope' => 'university'],
        ]])->assertRedirect();
        $this->assertSame('manager', $manager->fresh()->access_level);
        $this->assertSame('university', $manager->fresh()->scope);

        $adminPost = OrgNode::query()->where('system_key', 'national-administrators')->firstOrFail();

        $this->actingAs($admin)->post('/admin/structure/postes/droits', ['rows' => [$adminPost->id => ['scope' => '', 'permissions' => ['']]]])
            ->assertSessionHasErrors('access_level');

        $this->assertSame('global_admin', $adminPost->fresh()->access_level);
    }

    public function test_custom_position_rights_apply_only_inside_the_position_perimeter(): void
    {
        $admin = $this->admin();
        $holder = User::factory()->create();
        $position = $this->position('Poste ciblé');

        $this->actingAs($admin)->post('/admin/structure/postes/droits', ['rows' => [
            $position->id => ['permissions' => ['membership.manage'], 'scope' => 'high-school'],
        ]])->assertRedirect();

        $person = OrgPerson::query()->create(['user_id' => $holder->id, 'full_name' => $holder->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $grants = \App\Support\OrgAccess::grants($holder);

        $this->assertSame(['membership.manage'], array_keys($grants));
        $this->assertSame(['high-school'], $grants['membership.manage']);

        $this->actingAs($admin)->post('/admin/structure/postes/droits', ['rows' => [
            $position->id => ['permissions' => ['membership.manage'], 'scope_all' => 1, 'scope' => ''],
        ]])->assertRedirect();

        $this->assertSame([null], \App\Support\OrgAccess::grants($holder)['membership.manage']);
    }

    public function test_a_position_can_act_on_several_perimeters(): void
    {
        $admin = $this->admin();
        $holder = User::factory()->create();
        $position = $this->position('Poste multi-périmètres');

        $this->actingAs($admin)->post('/admin/structure/postes/droits', ['rows' => [
            $position->id => ['permissions' => ['membership.manage'], 'scope' => 'high-school'],
        ]])->assertRedirect();

        $person = OrgPerson::query()->create(['user_id' => $holder->id, 'full_name' => $holder->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $this->actingAs($admin)->put("/admin/structure/postes/{$position->id}", [
            'title' => $position->title,
            'scope' => 'high-school',
            'extra_scopes_present' => 1,
            'extra_scopes' => ['alumni', 'high-school', 'alumni'],
        ])->assertRedirect();

        $this->assertSame(['alumni'], $position->fresh()->extra_scopes);
        $this->assertEqualsCanonicalizing(['high-school', 'alumni'], \App\Support\OrgAccess::grants($holder)['membership.manage']);
        $this->assertTrue(\App\Support\AccessScope::can($holder, 'membership.manage', 'alumni'));
        $this->assertFalse(\App\Support\AccessScope::can($holder, 'membership.manage', 'university'));

        $this->actingAs($admin)->put("/admin/structure/postes/{$position->id}", [
            'title' => $position->title,
            'scope' => 'high-school',
            'extra_scopes_present' => 1,
        ])->assertRedirect();

        $this->assertNull($position->fresh()->extra_scopes);
        $this->assertSame(['high-school'], \App\Support\OrgAccess::grants($holder)['membership.manage']);

        $this->actingAs($admin)->put("/admin/structure/postes/{$position->id}", [
            'title' => $position->title,
            'extra_scopes_present' => 1,
            'extra_scopes' => ['inconnu'],
        ])->assertSessionHasErrors('extra_scopes.0');
    }

    public function test_holders_can_be_assigned_and_removed_from_the_sheet(): void
    {
        $admin = $this->admin();
        $position = $this->position('Poste unique');
        $first = User::factory()->create(['name' => 'Karim Test']);
        $second = User::factory()->create(['name' => 'Amel Test']);

        $this->actingAs($admin)->get("/admin/structure/postes/{$position->id}/modifier?u=Karim")->assertSee('Karim Test')->assertSee('Affecter');

        $this->actingAs($admin)->post("/admin/structure/postes/{$position->id}/titulaires", ['user_id' => $first->id])->assertRedirect();
        $this->assertSame(1, OrgAssignment::query()->where('node_id', $position->id)->count());

        $this->actingAs($admin)->post("/admin/structure/postes/{$position->id}/titulaires", ['user_id' => $second->id])
            ->assertSessionHasErrors('person_ids');
        $this->assertSame(1, OrgAssignment::query()->where('node_id', $position->id)->count());

        $person = OrgPerson::query()->where('user_id', $first->id)->firstOrFail();
        $this->actingAs($admin)->delete("/admin/structure/postes/{$position->id}/titulaires/{$person->id}")->assertRedirect();
        $this->assertSame(0, OrgAssignment::query()->where('node_id', $position->id)->count());
    }

    public function test_sub_blocks_can_be_added_and_deleted_from_the_sheet(): void
    {
        $admin = $this->admin();
        $club = $this->universityClub('Club Fiche', false);

        $this->actingAs($admin)->post('/admin/structure/postes', [
            'parent_id' => $club->id, 'title' => 'Président de test', 'kind' => 'position', 'capacity' => 'single', 'access_level' => 'club_president',
        ])->assertRedirect();

        $president = OrgNode::query()->where('title', 'Président de test')->firstOrFail();
        $this->assertSame('club_president', $president->access_level);
        $this->assertSame('Club Fiche', $president->subtitle);

        $this->actingAs($admin)->delete("/admin/structure/postes/{$president->id}")->assertRedirect("/admin/structure/postes/{$club->id}/modifier");
        $this->assertNull(OrgNode::query()->find($president->id));
    }

    public function test_clubs_table_shows_team_progress_and_creates_clubs_with_the_template(): void
    {
        $admin = $this->admin();
        $this->universityClub('Club Rempli');
        $empty = $this->universityClub('Club Vide', false);

        $this->actingAs($admin)->get('/admin/structure/clubs')
            ->assertOk()
            ->assertSee('Club Rempli')
            ->assertSee('Club Vide')
            ->assertSee('Aucune équipe')
            ->assertSee('0 / 13 postes pourvus');

        $this->actingAs($admin)->post("/admin/structure/postes/{$empty->id}/modele")->assertRedirect();
        $this->assertSame(13, OrgNode::query()->where('subtitle', 'Club Vide')->where('kind', 'position')->count());

        $this->actingAs($admin)->post('/admin/structure/clubs', ['name' => 'Nouveau Club', 'type' => 'highschool', 'apply_template' => '1'])->assertRedirect();
        $created = OrgNode::query()->where('kind', 'club')->where('title', 'Nouveau Club')->firstOrFail();
        $this->assertSame('highschool_club', $created->category);
        $this->assertSame(4, OrgNode::query()->where('parent_id', '!=', null)->where('subtitle', 'Nouveau Club')->where('kind', 'club_department')->count());

        $this->actingAs($admin)->post('/admin/structure/clubs/aligner')->assertRedirect();
    }

    public function test_menu_links_to_the_new_screens(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee(route('admin.postes.index'), false)
            ->assertSee(route('admin.clubs.index'), false);
    }
}
