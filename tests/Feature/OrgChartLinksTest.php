<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Entity;
use App\Models\Governorate;
use App\Models\Institution;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\OrgChart\OrgChartLinker;
use Database\Seeders\EntitySeeder;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * L'organigramme est relie aux entites, departements, institutions et clubs de la plateforme.
 */
class OrgChartLinksTest extends TestCase
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
    private function chart(?User $user = null): array
    {
        return $this->actingAs($user ?? $this->editor())->getJson('/organigramme/donnees')->assertOk()->json();
    }

    /** @param array<string, mixed> $chart */
    private function bySystemKey(array $chart, string $key): array
    {
        return collect($chart['nodes'])->firstWhere('system_key', $key);
    }

    /** @param array<string, mixed> $chart */
    private function byClub(array $chart, int $clubId): ?array
    {
        return collect($chart['nodes'])->firstWhere('club_id', $clubId);
    }

    private function legacyClub(string $name, string $entitySlug, string $governorateSlug, bool $withInstitution = true): Club
    {
        $entity = Entity::query()->where('slug', $entitySlug)->first();
        $institution = $withInstitution
            ? Institution::query()->create([
                'entity_id' => $entity->id,
                'name' => $name.' (établissement)',
                'slug' => str($name)->slug(),
                'institution_type' => $entitySlug === 'university' ? 'university' : 'high_school',
                'is_active' => true,
            ])
            : null;

        return Club::query()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'entity_id' => $entity->id,
            'institution_id' => $institution?->id,
            'governorate_id' => Governorate::query()->where('slug', $governorateSlug)->value('id'),
            'status' => 'active',
        ]);
    }

    public function test_system_nodes_are_linked_and_children_inherit(): void
    {
        $chart = $this->chart();

        $office = $this->bySystemKey($chart, 'university-office');
        $this->assertSame('University', $office['entity_name']);
        $this->assertSame('Bureau universitaire', $office['department_name']);
        $this->assertFalse($office['entity_inherited']);

        $manager = $this->bySystemKey($chart, 'university-manager-hr');
        $this->assertSame('University', $manager['entity_name']);
        $this->assertSame('Bureau universitaire', $manager['department_name']);
        $this->assertTrue($manager['entity_inherited']);
        $this->assertNull($manager['entity_id'], 'Le lien herite n\'est pas stocke sur le bloc enfant.');

        $this->assertSame('Alumni', $this->bySystemKey($chart, 'alumni')['entity_name']);
        $this->assertSame('High School', $this->bySystemKey($chart, 'highschool-office')['entity_name']);

        // Les clubs regionaux suivent leur club (entite Fondation).
        $tunis = $this->bySystemKey($chart, 'regional-club:tunis');
        $this->assertSame('Fondation', $tunis['entity_name']);
        $this->assertSame('We Are Tunis', $tunis['club_name']);
    }

    public function test_sync_creates_a_node_for_each_existing_university_and_highschool_club_grouped_by_governorate(): void
    {
        $uni = $this->legacyClub('Club ESPRIT', 'university', 'tunis');
        $uni2 = $this->legacyClub('Club ENIT', 'university', 'tunis');
        $lycee = $this->legacyClub('Club Lycée Pilote', 'high-school', 'sfax');

        // Cree avant l'organigramme : aucun bloc pour le moment.
        $chart = $this->chart();

        foreach ([$uni, $uni2, $lycee] as $club) {
            $this->assertNotNull($this->byClub($chart, $club->id), $club->name.' doit avoir un bloc');
        }

        $node = $this->byClub($chart, $uni->id);
        $group = collect($chart['nodes'])->firstWhere('id', $node['parent_id']);
        $this->assertSame('university-clubs:tunis', $group['system_key']);
        $this->assertSame('Tunis', $group['title']);
        $this->assertSame($this->bySystemKey($chart, 'university-clubs')['id'], $group['parent_id']);
        $this->assertSame($group['id'], $this->byClub($chart, $uni2->id)['parent_id'], 'Meme gouvernorat : meme groupe.');

        $this->assertSame('University', $node['entity_name']);
        $this->assertSame('Club ESPRIT (établissement)', $node['institution_name']);
        $this->assertSame('Tunis', $node['governorate_name']);

        $highschool = $this->byClub($chart, $lycee->id);
        $this->assertSame('highschool-clubs:sfax', collect($chart['nodes'])->firstWhere('id', $highschool['parent_id'])['system_key']);
    }

    public function test_sync_is_idempotent(): void
    {
        $this->legacyClub('Club ESPRIT', 'university', 'tunis');
        $this->chart();
        $count = OrgNode::query()->count();

        $stats = app(OrgChartLinker::class)->sync();

        $this->assertSame(0, $stats['created']);
        $this->assertSame(0, $stats['updated']);
        $this->assertSame($count, OrgNode::query()->count());
    }

    public function test_clubs_created_or_edited_later_follow_automatically(): void
    {
        $this->chart();

        $club = $this->legacyClub('Club Nouveau', 'university', 'ariana');
        $node = OrgNode::query()->where('club_id', $club->id)->first();
        $this->assertNotNull($node, 'Un club cree apres l\'installation obtient son bloc.');
        $this->assertSame('university-clubs:ariana', $node->parent->system_key);

        $club->update(['name' => 'Club Renomme', 'governorate_id' => Governorate::query()->where('slug', 'sfax')->value('id')]);
        $node->refresh();
        $this->assertSame('Club Renomme', $node->title);
        $this->assertSame('university-clubs:sfax', $node->parent->system_key, 'Le bloc suit le gouvernorat du club.');

        $institution = Institution::query()->create(['entity_id' => $club->entity_id, 'name' => 'Faculté X', 'slug' => 'faculte-x', 'institution_type' => 'university', 'is_active' => true]);
        $club->update(['institution_id' => $institution->id]);
        $this->assertSame($institution->id, $node->refresh()->institution_id);

        // Changement d'entite : le bloc change de branche.
        $club->update(['entity_id' => Entity::query()->where('slug', 'high-school')->value('id'), 'institution_id' => null]);
        $this->assertSame('highschool-clubs:sfax', $node->refresh()->parent->system_key);
    }

    public function test_creating_a_club_from_the_chart_creates_one_node_institution_and_department(): void
    {
        $this->chart();

        $response = $this->actingAs($this->editor())->postJson('/organigramme/clubs', [
            'name' => 'Club Sup\'Com',
            'type' => 'university',
            'governorate_id' => Governorate::query()->where('slug', 'tunis')->value('id'),
            'apply_template' => 0,
        ])->assertCreated();

        $club = Club::query()->where('name', 'Club Sup\'Com')->firstOrFail();
        $this->assertSame(1, OrgNode::query()->where('club_id', $club->id)->count());
        $this->assertNotNull($club->institution_id);
        $this->assertSame('bureau-universitaire', $club->department->slug);

        $node = collect($response->json('chart.nodes'))->firstWhere('club_id', $club->id);
        $this->assertSame($club->institution_id, $node['institution_id']);
        $this->assertSame($club->department_id, $node['department_id']);
    }

    public function test_editing_links_of_a_club_node_writes_through_to_the_club(): void
    {
        $club = $this->legacyClub('Club ESPRIT', 'university', 'tunis', false);
        $institution = Institution::query()->create(['entity_id' => $club->entity_id, 'name' => 'ESPRIT', 'slug' => 'esprit', 'institution_type' => 'university', 'is_active' => true]);
        $chart = $this->chart();
        $node = $this->byClub($chart, $club->id);

        $this->actingAs($this->editor())->patchJson('/organigramme/blocs/'.$node['id'], [
            'title' => $node['title'],
            'relation_type' => 'hierarchy',
            'parent_id' => $node['parent_id'],
            'validation_status' => 'confirmed',
            'children_layout' => 'horizontal',
            'is_collapsed' => 1,
            'governorate_id' => $node['governorate_id'],
            'entity_id' => $club->entity_id,
            'institution_id' => $institution->id,
        ])->assertOk();

        $this->assertSame($institution->id, $club->refresh()->institution_id);
        $this->assertSame($institution->id, OrgNode::query()->where('club_id', $club->id)->value('institution_id'));
    }

    public function test_inconsistent_links_are_rejected(): void
    {
        $chart = $this->chart();
        $office = $this->bySystemKey($chart, 'university-office');
        $foreignDepartment = Entity::query()->where('slug', 'alumni')->first()->departments()->first();

        $this->actingAs($this->editor())->patchJson('/organigramme/blocs/'.$office['id'], [
            'title' => $office['title'],
            'relation_type' => $office['relation_type'],
            'parent_id' => $office['parent_id'],
            'validation_status' => $office['validation_status'],
            'children_layout' => $office['children_layout'],
            'is_collapsed' => 0,
            'entity_id' => $office['entity_id'],
            'department_id' => $foreignDepartment->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('department_id');
    }

    public function test_admin_links_and_member_counts_are_reserved_to_editors(): void
    {
        $club = $this->legacyClub('Club ESPRIT', 'university', 'tunis');
        $member = User::factory()->create();
        Membership::query()->create(['user_id' => $member->id, 'entity_id' => $club->entity_id, 'club_id' => $club->id, 'status' => 'approved']);

        $editorNode = $this->byClub($this->chart(), $club->id);
        $this->assertSame(1, $editorNode['members_club']);
        $this->assertArrayHasKey('club', $editorNode['admin_urls']);
        $this->assertSame(1, $editorNode['members_institution']);

        $readerChart = $this->chart(User::factory()->create());
        $readerNode = $this->byClub($readerChart, $club->id);
        $this->assertNull($readerNode['members_club']);
        $this->assertSame([], $readerNode['admin_urls']);
        $this->assertSame([], $readerChart['meta']['institutions']);
        $this->assertSame('University', $readerNode['entity_name'], 'Les liens eux-memes restent lisibles.');
    }

    public function test_locate_redirects_to_the_linked_block(): void
    {
        $club = $this->legacyClub('Club ESPRIT', 'university', 'tunis');
        $chart = $this->chart();
        $node = $this->byClub($chart, $club->id);

        $this->actingAs($this->editor())->get('/organigramme/lien/club/'.$club->id)
            ->assertRedirect(route('orgchart.index').'?bloc='.$node['id']);

        $this->get('/organigramme/lien/institution/'.$club->institution_id)
            ->assertRedirect(route('orgchart.index').'?bloc='.$node['id']);

        $universityId = Entity::query()->where('slug', 'university')->value('id');
        $this->get('/organigramme/lien/entity/'.$universityId)
            ->assertRedirect(route('orgchart.index').'?bloc='.$this->bySystemKey($chart, 'university-office')['id']);

        $this->get('/organigramme/lien/unknown/1')->assertNotFound();
    }

    public function test_locate_without_linked_block_goes_back_with_an_error(): void
    {
        $this->chart();
        $entity = Entity::query()->create(['name' => 'Nouvelle', 'slug' => 'nouvelle', 'category' => 'education', 'is_active' => true]);

        $this->actingAs($this->editor())->from('/admin/entites')->get('/organigramme/lien/entity/'.$entity->id)
            ->assertRedirect('/admin/entites')
            ->assertSessionHas('error');
    }

    public function test_manual_sync_endpoint_adds_missing_nodes_and_requires_editor(): void
    {
        $this->chart();
        $club = $this->legacyClub('Club Manuel', 'university', 'tunis');
        OrgNode::query()->where('club_id', $club->id)->delete();

        $this->actingAs(User::factory()->create())->postJson('/organigramme/synchroniser')->assertForbidden();

        $response = $this->actingAs($this->editor())->postJson('/organigramme/synchroniser')->assertOk();
        $this->assertSame(1, $response->json('sync.created'));
        $this->assertNotNull($this->byClub($response->json('chart'), $club->id));
    }

    public function test_links_survive_a_json_export_and_import(): void
    {
        $club = $this->legacyClub('Club ESPRIT', 'university', 'tunis');
        $this->chart();
        $editor = $this->editor();

        $json = $this->actingAs($editor)->get('/organigramme/export.json')->assertOk()->getContent();
        $exported = collect(json_decode($json, true)['nodes']);
        $this->assertSame('university', $exported->firstWhere('system_key', 'university-office')['entity_slug']);
        $this->assertSame('bureau-universitaire', $exported->firstWhere('system_key', 'university-office')['department_slug']);

        OrgNode::query()->whereNotNull('entity_id')->update(['entity_id' => null, 'department_id' => null, 'institution_id' => null]);

        $file = UploadedFile::fake()->createWithContent('organigramme.json', $json);
        $this->actingAs($editor)->post('/organigramme/import', ['file' => $file, 'confirm' => 1], ['Accept' => 'application/json'])->assertOk();

        $office = OrgNode::query()->where('system_key', 'university-office')->first();
        $this->assertSame('university', $office->entity->slug);
        $this->assertSame('bureau-universitaire', $office->department->slug);
        $this->assertSame($club->institution_id, OrgNode::query()->where('club_id', $club->id)->value('institution_id'));

        $csv = $this->actingAs($editor)->get('/organigramme/export.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('gouvernorat;entite;departement;institution;validation', $csv);
    }

    public function test_users_can_be_searched_and_assigned_to_a_block(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Ben Salah', 'email' => 'alice@example.test']);
        $alice->profile()->create(['phone' => '+216 20 000 000']);
        User::factory()->create(['name' => 'Alice Inactive', 'email' => 'inactive@example.test', 'is_active' => false]);
        User::factory()->create(['name' => 'Alice Legacy', 'email' => 'legacy-9@legacy.invalid']);

        $chart = $this->chart();
        $editor = $this->editor();

        $found = $this->actingAs($editor)->getJson('/organigramme/utilisateurs?q=alice')->assertOk()->json('users');
        $names = collect($found)->pluck('name')->all();
        $this->assertContains('Alice Ben Salah', $names);
        $this->assertContains('Alice Legacy', $names);
        $this->assertNotContains('Alice Inactive', $names, 'Les comptes désactivés ne sont pas proposés.');
        $this->assertNull(collect($found)->firstWhere('name', 'Alice Legacy')['email'], 'Les e-mails de remplacement sont masqués.');

        $this->assertSame([], $this->actingAs($editor)->getJson('/organigramme/utilisateurs?q=a')->json('users'));
        $this->actingAs(User::factory()->create())->getJson('/organigramme/utilisateurs?q=alice')->assertForbidden();

        $director = $this->bySystemKey($chart, 'executive-director');
        $response = $this->actingAs($editor)->postJson('/organigramme/blocs/'.$director['id'].'/utilisateurs', ['user_id' => $alice->id])->assertOk();

        $person = collect($response->json('chart.people'))->firstWhere('user_id', $alice->id);
        $this->assertSame('Alice Ben Salah', $person['full_name']);
        $this->assertSame('alice@example.test', $person['email']);
        $this->assertSame('+216 20 000 000', $person['phone']);
        $this->assertContains($person['id'], collect($response->json('chart.nodes'))->firstWhere('id', $director['id'])['person_ids']);

        // Même utilisateur sur un second poste : une seule fiche personne.
        $office = $this->bySystemKey($chart, 'university-office');
        $this->actingAs($editor)->postJson('/organigramme/blocs/'.$office['id'].'/utilisateurs', ['user_id' => $alice->id])->assertOk();
        $this->assertSame(1, OrgPerson::query()->where('user_id', $alice->id)->count());
        $this->assertSame(2, OrgAssignment::query()->where('person_id', $person['id'])->count());

        // Le nom suit le compte.
        $alice->update(['name' => 'Alice Renommée']);
        $renamed = collect($this->chart()['people'])->firstWhere('user_id', $alice->id);
        $this->assertSame('Alice Renommée', $renamed['full_name']);
    }

    public function test_admin_user_page_lists_the_positions_held_in_the_chart(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Ben Salah']);
        $chart = $this->chart();
        $director = $this->bySystemKey($chart, 'executive-director');
        $editor = $this->editor();

        $this->actingAs($editor)->get('/admin/utilisateurs/'.$alice->id)->assertOk()->assertSee('Aucun poste dans l', false);

        $this->actingAs($editor)->postJson('/organigramme/blocs/'.$director['id'].'/utilisateurs', ['user_id' => $alice->id])->assertOk();

        $this->get('/admin/utilisateurs/'.$alice->id)->assertOk()
            ->assertSee('Directeur ex', false)
            ->assertSee('/organigramme?bloc='.$director['id'], false);
    }

    public function test_assigning_an_inactive_account_or_as_reader_is_refused(): void
    {
        $node = $this->bySystemKey($this->chart(), 'executive-director');
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($this->editor())->postJson('/organigramme/blocs/'.$node['id'].'/utilisateurs', ['user_id' => $inactive->id])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson('/organigramme/blocs/'.$node['id'].'/utilisateurs', ['user_id' => $inactive->id])->assertForbidden();
    }
}
