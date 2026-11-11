<?php

namespace Tests\Feature;

use App\Models\ClubActivity;
use App\Models\Challenge;
use App\Models\Mandate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ClubDashboardTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_club_page_shows_dashboard_memberships_by_mandate_and_department_and_activities(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin);

        $club = OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'university_club', 'title' => 'Club AAC', 'scope' => 'university',
            'status' => 'active', 'institution_name' => 'AAC Institut', 'sort_order' => 0,
        ]);
        $department = OrgNode::query()->create(['parent_id' => $club->id, 'relation_type' => 'hierarchy', 'kind' => 'club_department', 'category' => 'university_club', 'title' => 'Département Marketing', 'sort_order' => 0]);
        $position = OrgNode::query()->create([
            'parent_id' => $department->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'access_level' => 'none',
            'category' => 'university_club', 'title' => 'Chargé marketing', 'sort_order' => 0,
        ]);

        $holder = User::factory()->create(['name' => 'Titulaire']);
        $person = OrgPerson::query()->create(['user_id' => $holder->id, 'full_name' => 'Titulaire', 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $current = Mandate::query()->create(['label' => 'Mandat 2026-2027', 'start_year' => 2026, 'end_year' => 2027, 'bureau_name' => 'B', 'status' => 'current']);
        Mandate::query()->create(['label' => 'Mandat 2024-2025', 'start_year' => 2024, 'end_year' => 2025, 'bureau_name' => 'B', 'status' => 'closed']);

        Membership::query()->create(['user_id' => $holder->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved', 'membership_type' => 'member', 'starts_at' => '2026-10-01']);
        Membership::query()->create(['user_id' => User::factory()->create()->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved', 'membership_type' => 'member', 'starts_at' => '2026-10-02']);

        $challenge = Challenge::query()->create([
            'title' => 'Défi Campus', 'theme' => 'T', 'scope' => 'university', 'status' => 'published',
            'starts_at' => now()->subDays(30), 'ends_at' => now()->addDays(30), 'evaluation_at' => now()->addDays(32), 'results_at' => now()->addDays(34),
        ]);

        foreach (['Atelier à venir' => now()->addDays(5), 'Sortie passée' => now()->subDays(5)] as $title => $date) {
            ClubActivity::query()->create([
                'challenge_id' => $challenge->id, 'club_id' => $club->id, 'title' => $title, 'type' => 'event', 'audience' => 'public',
                'in_theme' => true, 'starts_at' => $date, 'ends_at' => $date->copy()->addHours(2), 'status' => 'published',
            ]);
        }

        $this->actingAs($admin->fresh())->get('/admin/structure/clubs/'.$club->id)
            ->assertOk()
            ->assertSee('Postes pourvus')
            ->assertSee('Niveau du club')
            ->assertSee('University Clubs')
            ->assertSee('Mandat 2026-2027')
            ->assertSee('Organigramme du club')
            ->assertSee('Département Marketing')
            ->assertSee('Sans département')
            ->assertSee('Adhésions par mandat et par département')
            ->assertSee('Atelier à venir')
            ->assertSee('Sortie passée')
            ->assertSee('AAC Institut')
            ->assertDontSee('Dernières publications');
    }
}
