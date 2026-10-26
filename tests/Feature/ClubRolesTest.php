<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\ClubRoles;
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

    public function test_clubs_without_a_team_receive_the_standard_team_and_roles(): void
    {
        $this->admin();
        $empty = $this->club('ESPRIT', false);
        $highschool = $this->club('Lycée Pilote', false, 'highschool_club', 'highschool-clubs');
        $regional = $this->club('We Are Tunis', false, 'regional_club', 'regional-clubs');

        $this->assertSame(0, OrgNode::query()->where('parent_id', $empty->id)->count());

        app(OrgChartInstaller::class)->alignClubTeams();

        foreach ([$empty, $highschool] as $club) {
            $team = OrgNode::query()->where('subtitle', $club->title)->get();
            $this->assertSame(13, $team->where('kind', 'position')->count());
            $this->assertSame(4, $team->where('kind', 'club_department')->count());
            $this->assertSame('club_president', $team->firstWhere('role_key', 'president')->access_level);
            $this->assertSame('multiple', $team->firstWhere('role_key', 'members-rd')->capacity);
        }

        // Les clubs regionaux gardent leur propre structure.
        $this->assertSame(0, OrgNode::query()->where('parent_id', $regional->id)->count());
    }

    public function test_existing_team_without_role_keys_is_tagged(): void
    {
        $this->admin();
        $club = $this->club('ESPRIT', true);
        OrgNode::query()->where('subtitle', $club->title)->update(['role_key' => null]);

        app(OrgChartInstaller::class)->tagClubRoles();

        $this->assertSame('vp-marketing', OrgNode::query()->where('subtitle', $club->title)->where('title', 'VP Marketing')->value('role_key'));
        $this->assertSame(0, OrgNode::query()->where('subtitle', $club->title)->whereNull('role_key')->count());
    }

    public function test_role_rights_are_defined_once_for_all_clubs(): void
    {
        $admin = $this->admin();
        $first = $this->club('ESPRIT', true);
        $second = $this->club('INSAT', true);

        $this->actingAs($admin)->get('/admin/structure/roles')->assertOk()->assertSee('VP Marketing')->assertSee('Droits des clubs');

        $levels = ClubRoles::levels();
        $levels['vp-events'] = 'moderator';
        $levels['assistant-events'] = 'manager';

        $this->actingAs($admin)->put('/admin/structure/roles', ['levels' => $levels])->assertSessionHasNoErrors();

        foreach ([$first, $second] as $club) {
            $team = OrgNode::query()->where('subtitle', $club->title);
            $this->assertSame('moderator', (clone $team)->where('role_key', 'vp-events')->value('access_level'));
            $this->assertSame('manager', (clone $team)->where('role_key', 'assistant-events')->value('access_level'));
        }

        // Un club cree ensuite suit le meme reglage.
        $third = $this->club('ENIT', true);
        $this->assertSame('moderator', OrgNode::query()->where('subtitle', $third->title)->where('role_key', 'vp-events')->value('access_level'));
    }

    public function test_a_club_role_cannot_receive_global_rights_and_only_global_admins_edit(): void
    {
        $admin = $this->admin();
        $this->club('ESPRIT', true);

        $this->actingAs($admin)->put('/admin/structure/roles', ['levels' => ['president' => 'global_admin']])->assertSessionHasErrors('levels.president');
        $this->assertSame('club_president', ClubRoles::level('president'));

        $this->actingAs(User::factory()->create())->put('/admin/structure/roles', ['levels' => ['president' => 'none']])->assertForbidden();
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
