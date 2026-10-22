<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ClubStatusTest extends TestCase
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

    private function club(string $name, string $status): OrgNode
    {
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $club = app(OrgChartInstaller::class)->createClub($branch, $name, 'university_club', Governorate::query()->first(), false);
        $club->update(['status' => $status]);

        return $club;
    }

    public function test_checked_clubs_change_status(): void
    {
        $admin = $this->admin();
        $one = $this->club('ESPRIT', 'inactive');
        $two = $this->club('INSAT', 'inactive');
        $three = $this->club('ENIT', 'inactive');

        $this->actingAs($admin)->post('/admin/structure/clubs/statut', ['status' => 'active', 'nodes' => [$one->id, $two->id]])->assertSessionHasNoErrors();

        $this->assertSame('active', $one->fresh()->status);
        $this->assertSame('active', $two->fresh()->status);
        $this->assertSame('inactive', $three->fresh()->status);
    }

    public function test_all_clubs_of_the_filter_change_status_across_pages(): void
    {
        $admin = $this->admin();

        foreach (range(1, 25) as $i) {
            $this->club('Club '.$i, 'inactive');
        }

        $this->actingAs($admin)->post('/admin/structure/clubs/statut', ['status' => 'active', 'all_filtered' => '1', 'filter_status' => 'inactive'])->assertSessionHasNoErrors();

        $this->assertSame(25, OrgNode::query()->where('kind', 'club')->where('category', 'university_club')->where('status', 'active')->count());
    }

    public function test_it_requires_a_selection_and_a_global_admin(): void
    {
        $admin = $this->admin();
        $club = $this->club('ESPRIT', 'inactive');

        $this->actingAs($admin)->post('/admin/structure/clubs/statut', ['status' => 'active'])->assertSessionHasErrors('nodes');
        $this->actingAs(User::factory()->create())->post('/admin/structure/clubs/statut', ['status' => 'active', 'nodes' => [$club->id]])->assertForbidden();
        $this->assertSame('inactive', $club->fresh()->status);
    }

    public function test_clubs_page_explains_that_inactive_clubs_are_hidden(): void
    {
        $admin = $this->admin();
        $this->club('ESPRIT', 'inactive');

        $this->actingAs($admin)->get('/admin/structure/clubs')
            ->assertOk()
            ->assertSee('Inactif : 1')
            ->assertSee('statistiques');
    }

    public function test_legacy_flag_is_only_used_when_the_club_is_created(): void
    {
        $this->admin();
        $club = $this->club('ESPRIT', 'active');
        $club->update(['legacy_id' => 149]);

        $dump = tempnam(sys_get_temp_dir(), 'club');
        file_put_contents($dump, "INSERT INTO `tbl_club` (`clubID`, `name`, `birthday`, `city`, `facebook`, `email`, `is_Actif`, `charte`, `SenJun`) VALUES\n(149, 'ESPRIT', '2016-12-16', 'Ariana', 'a@b.tn', 'https://www.facebook.com/x/', '0', 1, 3);\n");

        app(\App\Support\LegacyClubImporter::class)->import($dump);
        unlink($dump);

        $this->assertSame('active', $club->fresh()->status);
    }
}
