<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\OrgChart\ClubTemplates;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ClubRolesTest extends TestCase
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

    private function club(string $name, bool $template, string $category = 'university_club', string $branchKey = 'university-clubs'): OrgNode
    {
        $branch = OrgNode::query()->where('system_key', $branchKey)->firstOrFail();

        return app(OrgChartInstaller::class)->createClub($branch, $name, $category, Governorate::query()->first(), $template);
    }

    public function test_clubs_without_a_team_receive_the_template_of_their_type(): void
    {
        $this->admin();
        $empty = $this->club('ESPRIT', false);
        $highschool = $this->club('Lycée Pilote', false, 'highschool_club', 'highschool-clubs');
        $regional = $this->club('We Are Carthage Test', false, 'regional_club', 'regional-clubs');

        $this->assertSame(0, OrgNode::query()->where('parent_id', $empty->id)->count());

        app(OrgChartInstaller::class)->alignClubTeams();

        foreach ([$empty, $highschool] as $club) {
            $team = OrgNode::query()->where('subtitle', $club->title)->get();
            $this->assertSame(13, $team->where('kind', 'position')->count());
            $this->assertSame(4, $team->where('kind', 'club_department')->count());
            $this->assertSame('club_president', $team->firstWhere('role_key', 'president')->access_level);
            $this->assertSame('multiple', $team->firstWhere('role_key', 'members-rd')->capacity);
        }

        // Les clubs We Are Carthage ont leur propre modele : president, 4 departements avec VP, assistants et membres.
        $team = OrgNode::query()->where('subtitle', $regional->title)->get();
        $this->assertSame(6, $team->where('kind', 'position')->count());
        $this->assertSame(4, $team->where('kind', 'club_department')->count());
        $this->assertSame('Président du club régional', $team->firstWhere('role_key', 'president')->title);
        $this->assertNull($team->firstWhere('role_key', 'assistant-marketing'));
    }

    public function test_existing_team_without_role_keys_is_tagged(): void
    {
        $this->admin();
        $club = $this->club('ESPRIT', true);
        OrgNode::query()->where('subtitle', $club->title)->update(['role_key' => null]);

        app(OrgChartInstaller::class)->alignClubTeams();

        $this->assertSame('vp-marketing', OrgNode::query()->where('subtitle', $club->title)->where('title', 'VP Marketing')->value('role_key'));
        $this->assertSame(0, OrgNode::query()->where('subtitle', $club->title)->whereNull('role_key')->count());
        $this->assertSame(0, app(OrgChartInstaller::class)->alignClubTeams());
    }

    public function test_the_template_of_each_type_is_edited_once_and_every_club_follows(): void
    {
        $admin = $this->admin();
        $first = $this->club('ESPRIT', true);
        $second = $this->club('INSAT', true);
        $lycee = $this->club('Lycée Pilote', true, 'highschool_club', 'highschool-clubs');

        $this->actingAs($admin)->get('/admin/structure/modeles/university_club')->assertOk()->assertSee('VP Marketing')->assertSee('Modèles de clubs');
        $this->actingAs($admin)->get('/admin/structure/modeles/regional_club')->assertOk()->assertSee('Président du club régional');
        $this->actingAs($admin)->get('/admin/structure/modeles/inconnu')->assertNotFound();

        $slots = collect(ClubTemplates::slots('university_club'))->map(fn (array $slot): array => [
            'title' => $slot['title'], 'parent_key' => $slot['parent'], 'capacity' => $slot['capacity'], 'access_level' => $slot['access'],
        ])->all();
        $slots['vp-events']['access_level'] = 'moderator';
        $slots['assistant-events']['access_level'] = 'manager';
        $slots['vp-events']['title'] = 'Vice-président Événements';

        $this->actingAs($admin)->put('/admin/structure/modeles/university_club', ['slots' => $slots])->assertSessionHasNoErrors();

        foreach ([$first, $second] as $club) {
            $team = OrgNode::query()->where('subtitle', $club->title);
            $this->assertSame('moderator', (clone $team)->where('role_key', 'vp-events')->value('access_level'));
            $this->assertSame('manager', (clone $team)->where('role_key', 'assistant-events')->value('access_level'));
            $this->assertSame('Vice-président Événements', (clone $team)->where('role_key', 'vp-events')->value('title'));
        }

        // Un autre type de club n'est pas touche, et un club cree ensuite suit le meme reglage.
        $this->assertSame('none', OrgNode::query()->where('subtitle', $lycee->title)->where('role_key', 'vp-events')->value('access_level'));
        $third = $this->club('ENIT', true);
        $this->assertSame('moderator', OrgNode::query()->where('subtitle', $third->title)->where('role_key', 'vp-events')->value('access_level'));
    }

    public function test_a_slot_added_to_the_template_is_created_in_every_club_and_removed_when_empty(): void
    {
        $admin = $this->admin();
        $first = $this->club('ESPRIT', true);
        $second = $this->club('INSAT', true);

        $this->actingAs($admin)->post('/admin/structure/modeles/university_club/blocs', [
            'title' => 'Trésorier', 'kind' => 'position', 'parent_key' => 'president', 'capacity' => 'single', 'access_level' => 'manager',
        ])->assertSessionHasNoErrors();

        foreach ([$first, $second] as $club) {
            $treasurer = OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'tresorier')->first();
            $this->assertNotNull($treasurer);
            $this->assertSame('manager', $treasurer->access_level);
            $this->assertSame('president', OrgNode::query()->find($treasurer->parent_id)->role_key);
        }

        // Un intitule deja pris est refuse.
        $this->actingAs($admin)->post('/admin/structure/modeles/university_club/blocs', ['title' => 'trésorier', 'kind' => 'position'])->assertSessionHasErrors('title');

        // Un titulaire dans un club : le poste y reste (sans role) quand il quitte le modele.
        $held = OrgNode::query()->where('subtitle', $first->title)->where('role_key', 'tresorier')->firstOrFail();
        $person = OrgPerson::query()->create(['user_id' => User::factory()->create()->id, 'full_name' => 'Titulaire', 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $held->id, 'person_id' => $person->id]);

        $this->actingAs($admin)->delete('/admin/structure/modeles/university_club/blocs/tresorier')->assertSessionHasNoErrors();

        $this->assertNotNull($held->fresh());
        $this->assertNull($held->fresh()->role_key);
        $this->assertSame(0, OrgNode::query()->where('subtitle', $second->title)->where('title', 'Trésorier')->count());
    }

    public function test_a_template_block_with_children_cannot_be_removed_and_cycles_are_refused(): void
    {
        $admin = $this->admin();
        $this->club('ESPRIT', true);

        $this->actingAs($admin)->delete('/admin/structure/modeles/university_club/blocs/dept-marketing')->assertSessionHasErrors('slot');

        $slots = collect(ClubTemplates::slots('university_club'))->map(fn (array $slot): array => [
            'title' => $slot['title'], 'parent_key' => $slot['parent'], 'capacity' => $slot['capacity'], 'access_level' => $slot['access'],
        ])->all();
        $slots['president']['parent_key'] = 'vp-marketing';

        $this->actingAs($admin)->put('/admin/structure/modeles/university_club', ['slots' => $slots])->assertSessionHasErrors('slots.president.parent_key');
    }

    public function test_a_club_role_cannot_receive_global_rights_and_only_global_admins_edit(): void
    {
        $admin = $this->admin();
        $this->club('ESPRIT', true);

        $slots = collect(ClubTemplates::slots('university_club'))->map(fn (array $slot): array => [
            'title' => $slot['title'], 'parent_key' => $slot['parent'], 'capacity' => $slot['capacity'], 'access_level' => $slot['access'],
        ])->all();
        $slots['president']['access_level'] = 'global_admin';

        $this->actingAs($admin)->put('/admin/structure/modeles/university_club', ['slots' => $slots])->assertSessionHasErrors('slots.president.access_level');
        $this->assertSame('club_president', ClubTemplates::slots('university_club')['president']['access']);

        $this->actingAs(User::factory()->create())->put('/admin/structure/modeles/university_club', ['slots' => $slots])->assertForbidden();
    }

    public function test_role_positions_ignore_bulk_and_sheet_level_changes(): void
    {
        $admin = $this->admin();
        $club = $this->club('ESPRIT', true);
        $vp = OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'vp-marketing')->firstOrFail();

        $this->actingAs($admin)->post('/admin/structure/postes/droits', ['rows' => [$vp->id => ['permissions' => ['membership.manage'], 'scope_all' => 1]]])->assertRedirect();
        $this->assertSame('none', $vp->fresh()->access_level);

        $this->actingAs($admin)->put('/admin/structure/postes/'.$vp->id, ['title' => $vp->title, 'capacity' => 'single', 'access_level' => 'manager'])->assertRedirect();
        $this->assertSame('none', $vp->fresh()->access_level);
    }

    public function test_club_page_shows_the_team_holders_and_activity(): void
    {
        $admin = $this->admin();
        $club = $this->club('ESPRIT', true);
        $president = OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'president')->firstOrFail();
        $member = User::factory()->create(['name' => 'Sana Ben Ali']);
        $person = OrgPerson::query()->create(['user_id' => $member->id, 'full_name' => 'Sana Ben Ali', 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $president->id, 'person_id' => $person->id]);

        $this->actingAs($admin)->get('/admin/structure/clubs/'.$club->id)
            ->assertOk()
            ->assertSee('ESPRIT')
            ->assertSee('Organigramme du club')
            ->assertSee('Sana Ben Ali')
            ->assertSee('VP Marketing')
            ->assertSee('Vacant')
            ->assertSee('1 / 13');

        $this->actingAs($admin)->get('/admin/structure/clubs/'.$president->id)->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/admin/structure/clubs/'.$club->id)->assertForbidden();
    }

    public function test_clubs_table_has_no_per_row_template_button_and_links_to_the_club_page(): void
    {
        $admin = $this->admin();
        $club = $this->club('ESPRIT', false);

        $this->actingAs($admin)->get('/admin/structure/clubs')
            ->assertOk()
            ->assertDontSee('Équipe type')
            ->assertSee('/admin/structure/clubs/'.$club->id, false);
    }
}
