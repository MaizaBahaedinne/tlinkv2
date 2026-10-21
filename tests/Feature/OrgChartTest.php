<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class OrgChartTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private ?User $editorUser = null;

    private function editor(): User
    {
        if ($this->editorUser) {
            return $this->editorUser;
        }

        $user = User::factory()->create();
        $this->grantPlatformAdmin($user);

        return $this->editorUser = $user;
    }

    private function install(): array
    {
        $this->seed(GovernorateSeeder::class);

        return $this->actingAs($this->editor())->getJson('/organigramme/donnees')->assertOk()->json();
    }

    /**
     * @param array<string, mixed> $chart
     */
    private function node(array $chart, string $systemKey): array
    {
        return collect($chart['nodes'])->firstWhere('system_key', $systemKey);
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function form(array $node, array $override = []): array
    {
        return $override + [
            'title' => $node['title'],
            'subtitle' => $node['subtitle'],
            'description' => $node['description'],
            'category' => $node['category'],
            'relation_type' => $node['relation_type'],
            'parent_id' => $node['parent_id'],
            'validation_status' => $node['validation_status'],
            'validation_note' => $node['validation_note'],
            'children_layout' => $node['children_layout'],
            'is_collapsed' => $node['is_collapsed'] ? 1 : 0,
            'governorate_id' => $node['governorate_id'],
        ];
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/organigramme')->assertRedirect(route('login'));
        $this->getJson('/organigramme/donnees')->assertUnauthorized();
    }

    public function test_page_is_served_to_any_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())->get('/organigramme')
            ->assertOk()
            ->assertSee('Organigramme de la Fondation We Are Carthage');
    }

    public function test_initial_chart_is_installed_with_24_regional_clubs_and_default_teams(): void
    {
        $chart = $this->install();

        $regional = collect($chart['nodes'])->where('category', 'regional_club')->where('kind', 'club');
        $this->assertCount(24, $regional);

        $tunis = $this->node($chart, 'regional-club:tunis');
        $this->assertSame('We Are Tunis', $tunis['title']);

        $team = collect($chart['nodes'])->where('subtitle', $tunis['title']);
        $this->assertGreaterThanOrEqual(5, $team->count());
        $this->assertTrue($team->contains(fn ($n) => str_contains($n['title'], 'Président')));

        // Aucune personne inventee : seul l'administrateur de test (un compte réel) figure dans l'organigramme.
        $this->assertSame([$this->editor()->id], collect($chart['people'])->pluck('user_id')->all());

        // Presidence : conseillers et ancien president rattaches, sans lien hierarchique avec le directeur.
        $presidency = $this->node($chart, 'presidency');
        $director = $this->node($chart, 'executive-director');
        $this->assertSame('attachment', $this->node($chart, 'president-advisors')['relation_type']);
        $this->assertSame('attachment', $this->node($chart, 'former-president')['relation_type']);
        $this->assertSame($presidency['id'], $this->node($chart, 'president-advisors')['parent_id']);
        $this->assertSame($presidency['id'], $director['parent_id']);

        // Branches de clubs separees.
        $this->assertNotNull($this->node($chart, 'university-clubs'));
        $this->assertNotNull($this->node($chart, 'highschool-clubs'));

        // Les deductions sont signalees comme "a valider".
        $this->assertTrue(collect($chart['nodes'])->contains('validation_status', 'to_validate'));
        $this->assertTrue(collect($chart['nodes'])->contains('validation_status', 'confirmed'));
    }

    public function test_install_is_idempotent(): void
    {
        $this->install();
        $count = OrgNode::query()->count();

        $this->actingAs($this->editor())->getJson('/organigramme/donnees')->assertOk();

        $this->assertSame($count, OrgNode::query()->count());
    }

    public function test_read_only_user_cannot_mutate_and_sees_flag(): void
    {
        $chart = $this->install();
        $reader = User::factory()->create();
        $node = $this->node($chart, 'executive-director');

        $this->actingAs($reader)->getJson('/organigramme/donnees')->assertOk()->assertJsonPath('can_edit', false);

        $this->actingAs($reader)->patchJson("/organigramme/blocs/{$node['id']}", $this->form($node, ['title' => 'Piraté']))->assertForbidden();
        $this->actingAs($reader)->postJson('/organigramme/disposition', ['positions' => []])->assertForbidden();
        $this->actingAs($reader)->postJson('/organigramme/personnes', ['full_name' => 'X', 'contact_visible' => 1])->assertForbidden();
        $this->actingAs($reader)->getJson('/organigramme/export.json')->assertForbidden();
        $this->actingAs($reader)->getJson('/organigramme/historique')->assertForbidden();
        $this->actingAs($reader)->delete("/organigramme/blocs/{$node['id']}")->assertForbidden();
    }

    public function test_editor_can_create_update_and_delete_a_position(): void
    {
        $chart = $this->install();
        $editor = $this->editor();
        $parent = $this->node($chart, 'university-office');

        $response = $this->actingAs($editor)->postJson('/organigramme/blocs', [
            'title' => 'Manager Partenariats',
            'kind' => 'position',
            'relation_type' => 'hierarchy',
            'parent_id' => $parent['id'],
            'validation_status' => 'confirmed',
            'children_layout' => 'horizontal',
            'is_collapsed' => 0,
        ])->assertCreated();

        $id = $response->json('selected_id');
        $created = collect($response->json('chart.nodes'))->firstWhere('id', $id);
        $this->assertSame($parent['id'], $created['parent_id']);
        $this->assertSame($parent['category'], $created['category']);

        $this->actingAs($editor)->patchJson("/organigramme/blocs/{$id}", $this->form($created, ['title' => 'Manager Partenariats & RSE']))->assertOk();
        $this->assertDatabaseHas('org_nodes', ['id' => $id, 'title' => 'Manager Partenariats & RSE']);

        $this->actingAs($editor)->deleteJson("/organigramme/blocs/{$id}")->assertOk();
        $this->assertDatabaseMissing('org_nodes', ['id' => $id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'orgchart.node.created', 'actor_id' => $editor->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'orgchart.node.deleted']);
    }

    public function test_cycles_second_root_and_unsafe_deletions_are_rejected(): void
    {
        $chart = $this->install();
        $editor = $this->editor();

        $director = $this->node($chart, 'executive-director');
        $office = $this->node($chart, 'university-office');
        $presidency = $this->node($chart, 'presidency');

        // Cycle : le directeur sous l'un de ses descendants.
        $this->actingAs($editor)->patchJson("/organigramme/blocs/{$director['id']}", $this->form($director, ['parent_id' => $office['id']]))
            ->assertUnprocessable();

        // Un bloc ne peut pas etre son propre parent.
        $this->actingAs($editor)->patchJson("/organigramme/blocs/{$office['id']}", $this->form($office, ['parent_id' => $office['id']]))
            ->assertUnprocessable();

        // Une seule racine.
        $this->actingAs($editor)->postJson('/organigramme/blocs', [
            'title' => 'Seconde racine',
            'kind' => 'position',
            'relation_type' => 'hierarchy',
            'parent_id' => null,
            'validation_status' => 'confirmed',
            'children_layout' => 'horizontal',
            'is_collapsed' => 0,
        ])->assertUnprocessable();

        // La racine n'est pas supprimable, un bloc avec enfants exige la cascade.
        $this->actingAs($editor)->deleteJson("/organigramme/blocs/{$presidency['id']}", ['cascade' => true])->assertUnprocessable();
        $this->actingAs($editor)->deleteJson("/organigramme/blocs/{$office['id']}")->assertUnprocessable();
        $this->assertDatabaseHas('org_nodes', ['id' => $office['id']]);

        // Cascade explicite : supprime le bloc et ses descendants.
        $before = OrgNode::query()->count();
        $this->actingAs($editor)->deleteJson("/organigramme/blocs/{$office['id']}", ['cascade' => true])->assertOk();
        $this->assertLessThan($before, OrgNode::query()->count());
        $this->assertDatabaseMissing('org_nodes', ['id' => $office['id']]);
    }

    public function test_node_can_be_moved_to_another_manager(): void
    {
        $chart = $this->install();
        $editor = $this->editor();

        $assistant = collect($chart['nodes'])->first(fn ($n) => str_contains($n['system_key'] ?? '', 'university') && str_contains($n['title'], 'Assistant'));
        $target = collect($chart['nodes'])->first(fn ($n) => str_contains($n['system_key'] ?? '', 'university') && str_contains($n['title'], 'Events'));

        $this->assertNotNull($assistant);
        $this->assertNotNull($target);

        $this->actingAs($editor)->patchJson("/organigramme/blocs/{$assistant['id']}", $this->form($assistant, ['parent_id' => $target['id']]))->assertOk();

        $this->assertDatabaseHas('org_nodes', ['id' => $assistant['id'], 'parent_id' => $target['id']]);
    }

    public function test_layout_positions_are_saved(): void
    {
        $chart = $this->install();
        $node = $this->node($chart, 'alumni');

        $this->actingAs($this->editor())->postJson('/organigramme/disposition', [
            'positions' => [['id' => $node['id'], 'dx' => 120, 'dy' => -40]],
        ])->assertOk();

        $this->assertDatabaseHas('org_nodes', ['id' => $node['id'], 'pos_dx' => 120, 'pos_dy' => -40]);
    }

    public function test_unlimited_clubs_can_be_added_with_default_team(): void
    {
        $this->install();
        $editor = $this->editor();

        $response = $this->actingAs($editor)->postJson('/organigramme/clubs', [
            'name' => 'We Are ESPRIT',
            'type' => 'university',
            'apply_template' => 1,
        ])->assertCreated();

        $id = $response->json('selected_id');
        $nodes = collect($response->json('chart.nodes'));
        $this->assertSame('university_club', $nodes->firstWhere('id', $id)['category']);
        $this->assertGreaterThanOrEqual(5, $nodes->where('subtitle', 'We Are ESPRIT')->count());
        $this->assertDatabaseHas('org_nodes', ['title' => 'We Are ESPRIT', 'kind' => 'club', 'scope' => 'university']);

        $this->actingAs($editor)->postJson('/organigramme/clubs', [
            'name' => 'We Are Lycée Pilote',
            'type' => 'highschool',
            'apply_template' => 0,
        ])->assertCreated();

        // Un club regional exige un gouvernorat.
        $this->actingAs($editor)->postJson('/organigramme/clubs', [
            'name' => 'We Are Nulle Part',
            'type' => 'regional',
            'apply_template' => 1,
        ])->assertUnprocessable();
    }

    public function test_people_assignments_and_contact_masking(): void
    {
        Storage::fake('public');
        $chart = $this->install();
        $editor = $this->editor();
        $node = $this->node($chart, 'executive-director');

        $response = $this->actingAs($editor)->post('/organigramme/personnes', [
            'full_name' => 'Personne de test',
            'job_title' => 'Directrice',
            'phone' => '+216 71 000 000',
            'email' => 'contact@example.test',
            'contact_visible' => 0,
            'assign_to_node' => $node['id'],
            'photo' => UploadedFile::fake()->image('p.jpg', 200, 200),
        ], ['Accept' => 'application/json'])->assertCreated();

        $person = OrgPerson::query()->where('full_name', 'Personne de test')->firstOrFail();
        Storage::disk('public')->assertExists($person->photo_path);
        $this->assertSame([$person->id], collect($response->json('chart.nodes'))->firstWhere('id', $node['id'])['person_ids']);

        // Un lecteur ne voit pas les coordonnees d'une personne a contact masque.
        $reader = collect($this->actingAs(User::factory()->create())->getJson('/organigramme/donnees')->json('people'))->firstWhere('full_name', 'Personne de test');
        $this->assertNull($reader['phone']);
        $this->assertNull($reader['email']);
        $this->assertSame('Personne de test', $reader['full_name']);

        $admin = collect($this->actingAs($editor)->getJson('/organigramme/donnees')->json('people'))->firstWhere('full_name', 'Personne de test');
        $this->assertSame('contact@example.test', $admin['email']);

        // Numero invalide refuse.
        $this->actingAs($editor)->postJson('/organigramme/personnes', [
            'full_name' => 'Mauvais tel',
            'phone' => '<script>',
            'contact_visible' => 1,
        ])->assertUnprocessable();

        // Suppression de la personne : photo supprimee, affectation retiree.
        $this->actingAs($editor)->deleteJson("/organigramme/personnes/{$person->id}")->assertOk();
        Storage::disk('public')->assertMissing($person->photo_path);
        $this->assertDatabaseCount('org_assignments', 1);
    }

    public function test_json_round_trip_and_csv_export(): void
    {
        $chart = $this->install();
        $editor = $this->editor();
        $nodeCount = count($chart['nodes']);

        $this->actingAs($editor)->postJson('/organigramme/personnes', [
            'full_name' => '=HYPERLINK("http://x")',
            'contact_visible' => 1,
            'assign_to_node' => $this->node($chart, 'executive-director')['id'],
        ])->assertCreated();

        $export = $this->actingAs($editor)->get('/organigramme/export.json')->assertOk();
        $json = json_decode($export->getContent(), true);
        $this->assertCount($nodeCount, $json['nodes']);

        $csv = $this->actingAs($editor)->get('/organigramme/export.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('We Are Tunis', $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);

        // Modification locale, puis re-import de l'export : retour a l'etat d'origine.
        $this->actingAs($editor)->postJson('/organigramme/blocs', [
            'title' => 'Bloc temporaire',
            'kind' => 'position',
            'relation_type' => 'hierarchy',
            'parent_id' => $this->node($chart, 'alumni')['id'],
            'validation_status' => 'confirmed',
            'children_layout' => 'horizontal',
            'is_collapsed' => 0,
        ])->assertCreated();

        $file = UploadedFile::fake()->createWithContent('organigramme.json', $export->getContent());
        $this->actingAs($editor)->post('/organigramme/import', ['file' => $file, 'confirm' => 1], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame($nodeCount, OrgNode::query()->count());
        $this->assertDatabaseMissing('org_nodes', ['title' => 'Bloc temporaire']);
        $this->assertDatabaseCount('org_people', 2);
    }

    public function test_import_rejects_invalid_structures(): void
    {
        $this->install();
        $editor = $this->editor();

        $bad = [
            'version' => 1,
            'nodes' => [
                ['id' => 1, 'parent_id' => 2, 'title' => 'A', 'relation_type' => 'hierarchy', 'kind' => 'position', 'category' => 'direction'],
                ['id' => 2, 'parent_id' => 1, 'title' => 'B', 'relation_type' => 'hierarchy', 'kind' => 'position', 'category' => 'direction'],
            ],
            'people' => [],
            'assignments' => [],
        ];

        $file = UploadedFile::fake()->createWithContent('bad.json', json_encode($bad));
        $this->actingAs($editor)->post('/organigramme/import', ['file' => $file, 'confirm' => 1], ['Accept' => 'application/json'])->assertUnprocessable();

        // Sans confirmation explicite : refuse.
        $good = UploadedFile::fake()->createWithContent('x.json', '{}');
        $this->actingAs($editor)->post('/organigramme/import', ['file' => $good], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertGreaterThan(30, OrgNode::query()->count());
    }

    public function test_history_lists_changes(): void
    {
        $chart = $this->install();
        $editor = $this->editor();
        $node = $this->node($chart, 'alumni');

        $this->actingAs($editor)->patchJson("/organigramme/blocs/{$node['id']}", $this->form($node, ['title' => 'Réseau Alumni WAC']))->assertOk();

        $this->actingAs($editor)->getJson('/organigramme/historique')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Organigramme : bloc modifié')
            ->assertJsonPath('data.0.actor', $editor->name);
    }
}
