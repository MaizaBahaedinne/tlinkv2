<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\OrgChart\OrgChartLinker;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/**
 * L'organigramme porte lui-meme le perimetre et les informations des clubs de la plateforme.
 */
class OrgChartLinksTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private ?User $editorUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernorateSeeder::class);
    }

    private function editor(): User
    {
        if ($this->editorUser) {
            return $this->editorUser;
        }

        $user = User::factory()->create();
        $this->grantPlatformAdmin($user);

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
    private function byId(array $chart, int $id): ?array
    {
        return collect($chart['nodes'])->firstWhere('id', $id);
    }

    private function club(string $title, string $scope, string $governorateSlug, array $extra = []): OrgNode
    {
        $this->editor();
        $branch = OrgNode::query()->where('system_key', $scope === 'high-school' ? 'highschool-clubs' : 'university-clubs')->firstOrFail();

        return OrgNode::query()->create($extra + [
            'parent_id' => $branch->id,
            'relation_type' => 'hierarchy',
            'kind' => 'club',
            'category' => $branch->category,
            'title' => $title,
            'scope' => $scope,
            'status' => 'active',
            'governorate_id' => Governorate::query()->where('slug', $governorateSlug)->value('id'),
            'sort_order' => 0,
        ]);
    }

    public function test_system_nodes_have_a_scope_and_children_inherit_it(): void
    {
        $chart = $this->chart();

        $office = $this->bySystemKey($chart, 'university-office');
        $this->assertSame('university', $office['scope']);
        $this->assertSame('University', $office['scope_name']);
        $this->assertFalse($office['scope_inherited']);

        $manager = $this->bySystemKey($chart, 'university-manager-hr');
        $this->assertSame('university', $manager['eff_scope']);
        $this->assertTrue($manager['scope_inherited']);
        $this->assertNull($manager['scope'], 'Le perimetre herite n\'est pas stocke sur le bloc enfant.');

        $this->assertSame('alumni', $this->bySystemKey($chart, 'alumni')['scope']);
        $this->assertSame('high-school', $this->bySystemKey($chart, 'highschool-office')['scope']);

        // Les clubs regionaux appartiennent a la Fondation.
        $tunis = $this->bySystemKey($chart, 'regional-club:tunis');
        $this->assertSame('foundation', $tunis['eff_scope']);
        $this->assertSame('club', $tunis['kind']);
    }

    public function test_sync_places_clubs_under_their_branch_grouped_by_governorate(): void
    {
        $this->chart();
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $group = OrgNode::query()->create([
            'parent_id' => $branch->id, 'relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => $branch->category,
            'title' => 'Tunis', 'system_key' => 'university-clubs:tunis', 'sort_order' => 0,
        ]);
        $sfaxGroup = OrgNode::query()->create([
            'parent_id' => $branch->id, 'relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => $branch->category,
            'title' => 'Sfax', 'system_key' => 'university-clubs:sfax', 'sort_order' => 1,
        ]);

        $esprit = $this->club('Club ESPRIT', 'university', 'sfax', ['parent_id' => $group->id]);

        $stats = app(OrgChartLinker::class)->sync();

        $this->assertSame(1, $stats['updated']);
        $this->assertSame($sfaxGroup->id, $esprit->refresh()->parent_id, 'Le club suit le groupe de son gouvernorat.');
        $this->assertSame(app(OrgChartLinker::class)->branchFor('university')[1], $esprit->category);

        // Un club range a la main hors groupe de gouvernorat n'est jamais deplace.
        $manual = $this->club('Club Manuel', 'university', 'tunis', ['parent_id' => $branch->id]);
        app(OrgChartLinker::class)->sync();
        $this->assertSame($branch->id, $manual->refresh()->parent_id);
    }

    public function test_sync_is_idempotent(): void
    {
        $this->chart();
        $this->club('Club ESPRIT', 'university', 'tunis');
        app(OrgChartLinker::class)->sync();
        $count = OrgNode::query()->count();

        $stats = app(OrgChartLinker::class)->sync();

        $this->assertSame(0, $stats['linked']);
        $this->assertSame(0, $stats['updated']);
        $this->assertSame($count, OrgNode::query()->count());
    }

    public function test_creating_a_club_from_the_chart_creates_one_club_node_with_its_scope(): void
    {
        $this->chart();

        $response = $this->actingAs($this->editor())->postJson('/organigramme/clubs', [
            'name' => 'Club Sup\'Com',
            'type' => 'university',
            'governorate_id' => Governorate::query()->where('slug', 'tunis')->value('id'),
            'apply_template' => 0,
        ])->assertCreated();

        $this->assertSame(1, OrgNode::query()->where('title', 'Club Sup\'Com')->count());
        $node = collect($response->json('chart.nodes'))->firstWhere('title', 'Club Sup\'Com');
        $this->assertSame('club', $node['kind']);
        $this->assertSame('university', $node['scope']);
        $this->assertSame('active', $node['status']);
        $this->assertSame('university', $node['institution_type']);
        $this->assertSame('Club Sup\'Com', $node['institution_name']);
    }

    public function test_club_fields_are_edited_on_the_node_and_ignored_on_other_kinds(): void
    {
        $club = $this->club('Club ESPRIT', 'university', 'tunis');
        $chart = $this->chart();
        $node = $this->byId($chart, $club->id);
        $office = $this->bySystemKey($chart, 'university-office');

        $base = fn (array $n): array => [
            'title' => $n['title'], 'relation_type' => $n['relation_type'], 'parent_id' => $n['parent_id'],
            'validation_status' => $n['validation_status'], 'children_layout' => $n['children_layout'],
            'is_collapsed' => 1, 'governorate_id' => $n['governorate_id'],
        ];

        $this->actingAs($this->editor())->patchJson('/organigramme/blocs/'.$node['id'], $base($node) + [
            'scope' => 'university',
            'status' => 'inactive',
            'founded_on' => '2019-04-02',
            'institution_name' => 'ESPRIT',
            'institution_type' => 'university',
            'email' => 'club@esprit.tn',
            'facebook_url' => 'https://www.facebook.com/esprit',
            'charter_signed' => 1,
        ])->assertOk();

        $club->refresh();
        $this->assertSame('inactive', $club->status);
        $this->assertSame('2019-04-02', $club->founded_on->toDateString());
        $this->assertSame('ESPRIT', $club->institution_name);
        $this->assertSame('club@esprit.tn', $club->email);
        $this->assertTrue($club->charter_signed);

        // Sur un bloc qui n'est pas un club, les champs propres aux clubs sont ignores.
        $this->actingAs($this->editor())->patchJson('/organigramme/blocs/'.$office['id'], $base($office) + [
            'scope' => 'university', 'status' => 'archived', 'email' => 'x@y.tn', 'charter_signed' => 1,
        ])->assertOk();

        $officeNode = OrgNode::query()->find($office['id']);
        $this->assertSame('active', $officeNode->status);
        $this->assertNull($officeNode->email);
        $this->assertFalse($officeNode->charter_signed);

        // Un perimetre inconnu est refuse.
        $this->actingAs($this->editor())->patchJson('/organigramme/blocs/'.$office['id'], $base($office) + ['scope' => 'inconnu'])
            ->assertUnprocessable()->assertJsonValidationErrors('scope');
    }

    public function test_member_counts_are_reserved_to_editors(): void
    {
        $club = $this->club('Club ESPRIT', 'university', 'tunis');
        $member = User::factory()->create();
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved']);

        $editorNode = $this->byId($this->chart(), $club->id);
        $this->assertSame(1, $editorNode['members_club']);

        $office = $this->bySystemKey($this->chart(), 'university-office');
        $this->assertSame(1, $office['members_scope']);

        $readerChart = $this->chart(User::factory()->create());
        $readerNode = $this->byId($readerChart, $club->id);
        $this->assertNull($readerNode['members_club']);
        $this->assertNull($readerNode['email']);
        $this->assertSame([], $readerChart['meta']['scopes']);
        $this->assertSame('University', $readerNode['scope_name'], 'Le perimetre reste lisible.');
    }

    public function test_manual_sync_endpoint_realigns_clubs_and_requires_editor(): void
    {
        $this->chart();
        $this->club('Club Manuel', 'university', 'tunis');

        $this->actingAs(User::factory()->create())->postJson('/organigramme/synchroniser')->assertForbidden();

        $this->actingAs($this->editor())->postJson('/organigramme/synchroniser')->assertOk()->assertJsonStructure(['sync' => ['linked', 'updated']]);
    }

    public function test_scope_and_club_data_survive_a_json_export_and_import(): void
    {
        $club = $this->club('Club ESPRIT', 'university', 'tunis', [
            'legacy_id' => 143, 'email' => 'club@esprit.tn', 'founded_on' => '2019-04-02',
            'institution_name' => 'ESPRIT', 'institution_type' => 'university', 'charter_signed' => true,
        ]);
        $member = User::factory()->create();
        $membership = Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved']);
        $this->chart();
        $editor = $this->editor();

        $json = $this->actingAs($editor)->get('/organigramme/export.json')->assertOk()->getContent();
        $exported = collect(json_decode($json, true)['nodes']);
        $this->assertSame('university', $exported->firstWhere('system_key', 'university-office')['scope']);
        $this->assertSame(143, $exported->firstWhere('title', 'Club ESPRIT')['legacy_id']);

        OrgNode::query()->update(['scope' => null]);

        $file = UploadedFile::fake()->createWithContent('organigramme.json', $json);
        $this->actingAs($editor)->post('/organigramme/import', ['file' => $file, 'confirm' => 1], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('university', OrgNode::query()->where('system_key', 'university-office')->value('scope'));

        $restored = OrgNode::query()->where('legacy_id', 143)->firstOrFail();
        $this->assertSame('club@esprit.tn', $restored->email);
        $this->assertSame('ESPRIT', $restored->institution_name);
        $this->assertTrue($restored->charter_signed);
        $this->assertSame('2019-04-02', $restored->founded_on->toDateString());

        // L'import reconstruit les blocs : les adhesions restent reliees au bon club.
        $this->assertSame($restored->id, $membership->refresh()->node_id);

        $csv = $this->actingAs($editor)->get('/organigramme/export.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('gouvernorat;perimetre;etablissement;validation', $csv);
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

    public function test_a_person_linked_to_an_account_is_that_account(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Ben Salah', 'email' => 'alice@example.test']);
        $alice->profile()->create(['phone' => '+216 20 000 000', 'photo_path' => 'profiles/avatars/alice.jpg']);
        $chart = $this->chart();
        $editor = $this->editor();
        $director = $this->bySystemKey($chart, 'executive-director');

        $response = $this->actingAs($editor)->postJson('/organigramme/blocs/'.$director['id'].'/utilisateurs', ['user_id' => $alice->id])->assertOk();
        $person = collect($response->json('chart.people'))->firstWhere('user_id', $alice->id);
        $this->assertTrue($person['from_account']);
        $this->assertSame('/storage/profiles/avatars/alice.jpg', $person['photo_url']);

        // Rien n'est copié : un changement du compte se voit immédiatement.
        $alice->update(['email' => 'alice.new@example.test']);
        $alice->profile->update(['phone' => '+216 99 999 999']);
        $fresh = collect($this->chart()['people'])->firstWhere('user_id', $alice->id);
        $this->assertSame('alice.new@example.test', $fresh['email']);
        $this->assertSame('+216 99 999 999', $fresh['phone']);

        // La fiche ne peut pas contredire le compte : seuls fonction, visibilité et notes sont éditables.
        $this->actingAs($editor)->patchJson('/organigramme/personnes/'.$person['id'], [
            'full_name' => 'Autre Nom',
            'email' => 'autre@example.test',
            'phone' => '000',
            'job_title' => 'Directrice',
            'contact_visible' => 0,
            'notes' => 'note',
        ])->assertOk();

        $after = collect($this->chart()['people'])->firstWhere('user_id', $alice->id);
        $this->assertSame('Alice Ben Salah', $after['full_name']);
        $this->assertSame('alice.new@example.test', $after['email']);
        $this->assertSame('Directrice', $after['job_title']);
        $this->assertFalse($after['contact_visible']);

        // Coordonnées masquées aux lecteurs quand la visibilité est désactivée.
        $reader = collect($this->chart(User::factory()->create())['people'])->firstWhere('id', $person['id']);
        $this->assertNull($reader['email']);
        $this->assertNull($reader['phone']);
        $this->assertSame('Alice Ben Salah', $reader['full_name']);
    }

    public function test_account_links_survive_a_json_export_and_import(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Ben Salah', 'email' => 'alice@example.test']);
        $chart = $this->chart();
        $editor = $this->editor();
        $director = $this->bySystemKey($chart, 'executive-director');
        $this->actingAs($editor)->postJson('/organigramme/blocs/'.$director['id'].'/utilisateurs', ['user_id' => $alice->id])->assertOk();

        $json = $this->actingAs($editor)->get('/organigramme/export.json')->assertOk()->getContent();
        $file = UploadedFile::fake()->createWithContent('organigramme.json', $json);
        $this->actingAs($editor)->post('/organigramme/import', ['file' => $file, 'confirm' => 1], ['Accept' => 'application/json'])->assertOk();

        $this->assertTrue(OrgPerson::query()->where('user_id', $alice->id)->exists());
    }

    public function test_assigning_an_inactive_account_or_as_reader_is_refused(): void
    {
        $node = $this->bySystemKey($this->chart(), 'executive-director');
        $inactive = User::factory()->create(['is_active' => false]);

        $this->actingAs($this->editor())->postJson('/organigramme/blocs/'.$node['id'].'/utilisateurs', ['user_id' => $inactive->id])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson('/organigramme/blocs/'.$node['id'].'/utilisateurs', ['user_id' => $inactive->id])->assertForbidden();
    }
}
