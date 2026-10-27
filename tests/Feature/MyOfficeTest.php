<?php

namespace Tests\Feature;

use App\Models\AppModule;
use App\Models\AppNotification;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyOfficeTest extends TestCase
{
    use RefreshDatabase;

    private OrgNode $top;

    private OrgNode $middle;

    private OrgNode $low;

    private OrgNode $attached;

    protected function setUp(): void
    {
        parent::setUp();

        $this->top = $this->node('Directeur du bureau', null, 'manager', 'university');
        $this->middle = $this->node('Chef de pôle', $this->top);
        $this->low = $this->node('Assistant de pôle', $this->middle);
        $this->attached = $this->node('Conseiller rattaché', $this->top, 'none', null, 'attachment');
    }

    private function node(string $title, ?OrgNode $parent, string $level = 'none', ?string $scope = null, string $relation = 'hierarchy'): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => $parent?->id,
            'relation_type' => $relation,
            'kind' => 'position',
            'capacity' => 'multiple',
            'access_level' => $level,
            'category' => 'direction',
            'title' => $title,
            'scope' => $scope,
            'sort_order' => 0,
        ]);
    }

    private function place(OrgNode $node, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $person = OrgPerson::query()->create(['user_id' => $user->id, 'full_name' => $name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $node->id, 'person_id' => $person->id]);

        return $user;
    }

    public function test_the_page_lists_superiors_above_and_the_team_below(): void
    {
        $this->place($this->top, 'Karim Directeur');
        $middle = $this->place($this->middle, 'Sonia Cheffe');
        $this->place($this->low, 'Yassine Assistant');

        $this->actingAs($middle)->get('/mon-bureau')->assertOk()
            ->assertSee('Mes supérieurs')
            ->assertSee('Directeur du bureau')
            ->assertSee('Karim Directeur')
            ->assertSee('Assistant de pôle')
            ->assertSee('Yassine Assistant')
            ->assertDontSee('Conseiller rattaché');
    }

    public function test_only_positions_are_listed_and_ordered_by_level(): void
    {
        $unit = $this->node('Unité Alpha', $this->top);
        $unit->update(['kind' => 'unit']);
        $department = $this->node('Département Beta', $this->top);
        $department->update(['kind' => 'department']);

        // Sous mon poste : un chef via une unité (niveau 1), puis son adjoint (niveau 2), puis un autre chef direct (niveau 1).
        $first = $this->node('Chef Premier', $unit);
        $deputy = $this->node('Adjoint Profond', $first);
        $second = $this->node('Chef Second', $department);
        $first->update(['sort_order' => 1]);
        $second->update(['sort_order' => 2]);

        $html = $this->actingAs($this->place($this->top, 'Karim Directeur'))->get('/mon-bureau')->assertOk()
            ->assertDontSee('Unité Alpha')
            ->assertDontSee('Département Beta')
            ->assertSee('Niveau −1')
            ->assertSee('Niveau −2')
            ->getContent();

        $this->assertLessThan(strpos($html, 'Adjoint Profond'), strpos($html, 'Chef Second'), 'le niveau 1 passe avant le niveau 2');
        $this->assertLessThan(strpos($html, 'Chef Second'), strpos($html, 'Chef Premier'));
    }

    public function test_an_attached_position_has_no_hierarchical_superior(): void
    {
        $this->place($this->top, 'Karim Directeur');
        $advisor = $this->place($this->attached, 'Lina Conseillère');

        $this->actingAs($advisor)->get('/mon-bureau')->assertOk()
            ->assertSee('sans lien hiérarchique')
            ->assertDontSee('Karim Directeur');
    }

    public function test_a_superior_assigns_and_removes_holders_below_their_position(): void
    {
        $boss = $this->place($this->top, 'Karim Directeur');
        $candidate = User::factory()->create(['name' => 'Nour Candidate']);

        $this->actingAs($boss)->get('/mon-bureau?node='.$this->low->id.'&q=Nour')->assertOk()->assertSee('Nour Candidate')->assertSee('Affecter');

        $this->actingAs($boss)->post("/mon-bureau/postes/{$this->low->id}/titulaires", ['user_id' => $candidate->id])->assertRedirect('/mon-bureau');

        $person = OrgPerson::query()->where('user_id', $candidate->id)->firstOrFail();
        $this->assertTrue(OrgAssignment::query()->where('node_id', $this->low->id)->where('person_id', $person->id)->exists());
        $this->assertTrue(AppNotification::query()->where('user_id', $candidate->id)->where('type', 'office.assigned')->exists());

        $this->actingAs($boss)->delete("/mon-bureau/postes/{$this->low->id}/titulaires/{$person->id}")->assertRedirect('/mon-bureau');
        $this->assertFalse(OrgAssignment::query()->where('node_id', $this->low->id)->where('person_id', $person->id)->exists());
    }

    public function test_nobody_manages_their_own_level_above_or_a_simple_attachment(): void
    {
        $boss = $this->place($this->top, 'Karim Directeur');
        $middle = $this->place($this->middle, 'Sonia Cheffe');
        $worker = $this->place($this->low, 'Yassine Assistant');
        $candidate = User::factory()->create();

        $this->actingAs($worker)->post("/mon-bureau/postes/{$this->middle->id}/titulaires", ['user_id' => $candidate->id])->assertForbidden();
        $this->actingAs($middle)->post("/mon-bureau/postes/{$this->top->id}/titulaires", ['user_id' => $candidate->id])->assertForbidden();
        $this->actingAs($middle)->post("/mon-bureau/postes/{$this->middle->id}/titulaires", ['user_id' => $candidate->id])->assertForbidden();
        $this->actingAs($boss)->post("/mon-bureau/postes/{$this->top->id}/titulaires", ['user_id' => $candidate->id])->assertForbidden();
        $this->actingAs($boss)->post("/mon-bureau/postes/{$this->attached->id}/titulaires", ['user_id' => $candidate->id])->assertForbidden();

        $this->assertSame(0, OrgAssignment::query()->whereIn('node_id', [$this->top->id, $this->attached->id])->whereHas('person', fn ($q) => $q->where('user_id', $candidate->id))->count());
    }

    public function test_a_superior_cannot_hand_out_rights_they_do_not_have(): void
    {
        $boss = $this->place($this->top, 'Karim Directeur');
        $candidate = User::factory()->create();

        // Poste inférieur qui accorde un périmètre que le supérieur n'a pas, puis un niveau supérieur au sien.
        $otherScope = $this->node('Responsable alumni', $this->middle, 'manager', 'alumni');
        $global = $this->node('Admin global déguisé', $this->middle, 'global_admin');
        // Poste inférieur dont les droits restent dans le périmètre du supérieur : autorisé.
        $sameScope = $this->node('Responsable université', $this->middle, 'manager', 'university');

        $this->actingAs($boss)->post("/mon-bureau/postes/{$otherScope->id}/titulaires", ['user_id' => $candidate->id])->assertForbidden();
        $this->actingAs($boss)->post("/mon-bureau/postes/{$global->id}/titulaires", ['user_id' => $candidate->id])->assertForbidden();
        $this->actingAs($boss)->post("/mon-bureau/postes/{$sameScope->id}/titulaires", ['user_id' => $candidate->id])->assertRedirect('/mon-bureau');
    }

    public function test_a_single_holder_position_refuses_a_second_person(): void
    {
        $boss = $this->place($this->top, 'Karim Directeur');
        $this->low->update(['capacity' => 'single']);
        $this->place($this->low, 'Yassine Assistant');
        $candidate = User::factory()->create();

        $this->actingAs($boss)->post("/mon-bureau/postes/{$this->low->id}/titulaires", ['user_id' => $candidate->id])->assertSessionHasErrors('person_ids');
    }

    public function test_the_module_can_be_switched_off_in_the_catalog(): void
    {
        $middle = $this->place($this->middle, 'Sonia Cheffe');

        $this->actingAs($middle)->get('/mon-bureau')->assertOk();

        AppModule::query()->where('key', 'organization.my_office')->update(['is_active' => false]);

        $this->actingAs($middle)->get('/mon-bureau')->assertNotFound();
    }

    public function test_the_menu_link_only_shows_for_position_holders(): void
    {
        $holder = $this->place($this->middle, 'Sonia Cheffe');
        $other = User::factory()->create();

        $this->actingAs($holder)->get('/profil')->assertOk()->assertSee('/mon-bureau', false);
        $this->actingAs($other)->get('/profil')->assertOk()->assertDontSee('/mon-bureau', false);
        $this->actingAs($other)->get('/mon-bureau')->assertOk()->assertSee('aucun poste');
    }
}
