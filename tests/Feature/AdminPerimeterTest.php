<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ClubActivity;
use App\Models\FoundationEvent;
use App\Models\IdentityVerification;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\PositionApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Les pages d'administration n'affichent que les données des périmètres autorisés. */
class AdminPerimeterTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function club(string $title, string $scope = 'university', string $category = 'university_club'): OrgNode
    {
        return OrgNode::query()->create([
            'relation_type' => 'hierarchy', 'kind' => 'club', 'category' => $category,
            'title' => $title, 'scope' => $scope, 'status' => 'active', 'sort_order' => 0,
        ]);
    }

    private function holdInClub(User $user, OrgNode $club, string $level = 'club_president'): void
    {
        $position = OrgNode::query()->create([
            'parent_id' => $club->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple',
            'access_level' => $level, 'category' => 'direction', 'title' => 'Poste '.$level, 'sort_order' => 0,
        ]);

        $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], ['full_name' => $user->name, 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);
    }

    private function event(string $title, string $scope, ?OrgNode $club = null): FoundationEvent
    {
        return FoundationEvent::query()->create([
            'title' => $title, 'scope' => $scope, 'node_id' => $club?->id, 'status' => 'published', 'starts_at' => now()->addDays(3),
        ]);
    }

    private function challenge(string $title, string $scope): Challenge
    {
        return Challenge::query()->create([
            'title' => $title, 'theme' => 'Thème', 'scope' => $scope, 'status' => 'published',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10), 'evaluation_at' => now()->addDays(12), 'results_at' => now()->addDays(14),
        ]);
    }

    private function activity(Challenge $challenge, OrgNode $club, string $title): ClubActivity
    {
        return ClubActivity::query()->create([
            'challenge_id' => $challenge->id, 'club_id' => $club->id, 'title' => $title, 'type' => 'event', 'audience' => 'public',
            'in_theme' => true, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(3), 'status' => 'published',
        ]);
    }

    public function test_scope_admin_only_sees_the_clubs_of_their_scope(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');
        $this->club('Club Robotique');
        $this->club('Club Alumni Paris', 'alumni', 'regional_club');

        $this->actingAs($admin->fresh())->get('/admin/structure/clubs')
            ->assertOk()
            ->assertSee('Club Robotique')
            ->assertDontSee('Club Alumni Paris');
    }

    public function test_scope_admin_cannot_open_a_club_outside_their_scope(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');
        $mine = $this->club('Club Robotique');
        $foreign = $this->club('Club Alumni Paris', 'alumni', 'regional_club');

        $this->actingAs($admin->fresh())->get("/admin/structure/clubs/{$mine->id}")->assertOk();
        $this->actingAs($admin->fresh())->get("/admin/structure/clubs/{$foreign->id}")->assertForbidden();
    }

    public function test_global_admin_sees_every_club(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin);
        $this->club('Club Robotique');
        $this->club('Club Alumni Paris', 'alumni', 'regional_club');

        $this->actingAs($admin->fresh())->get('/admin/structure/clubs')
            ->assertOk()
            ->assertSee('Club Robotique')
            ->assertSee('Club Alumni Paris');
    }

    public function test_events_are_limited_to_the_authorized_scope(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');
        $mine = $this->event('Hackathon Université', 'university');
        $foreign = $this->event('Gala Alumni', 'alumni');

        $this->actingAs($admin->fresh())->get('/admin/evenements')
            ->assertOk()
            ->assertSee('Hackathon Université')
            ->assertDontSee('Gala Alumni');

        $this->actingAs($admin->fresh())->get("/admin/evenements/{$mine->id}")->assertOk();
        $this->actingAs($admin->fresh())->get("/admin/evenements/{$foreign->id}")->assertForbidden();
    }

    public function test_club_president_only_sees_events_of_their_club(): void
    {
        $leader = User::factory()->create();
        $mine = $this->club('Club Robotique');
        $other = $this->club('Club Théâtre');
        $this->holdInClub($leader, $mine);

        $own = $this->event('Atelier Robotique', 'university', $mine);
        $foreign = $this->event('Pièce Théâtre', 'university', $other);
        $this->event('Gala Université', 'university');

        $this->actingAs($leader->fresh())->get('/admin/evenements')
            ->assertOk()
            ->assertSee('Atelier Robotique')
            ->assertDontSee('Pièce Théâtre')
            ->assertDontSee('Gala Université');

        $this->actingAs($leader->fresh())->get("/admin/evenements/{$own->id}")->assertOk();
        $this->actingAs($leader->fresh())->get("/admin/evenements/{$foreign->id}")->assertForbidden();
    }

    public function test_challenges_are_limited_to_the_authorized_scope(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');
        $mine = $this->challenge('Défi Campus', 'university');
        $foreign = $this->challenge('Défi Alumni', 'alumni');

        $this->actingAs($admin->fresh())->get('/admin/defis')
            ->assertOk()
            ->assertSee('Défi Campus')
            ->assertDontSee('Défi Alumni');

        $this->actingAs($admin->fresh())->get("/admin/defis/{$mine->id}")->assertOk();
        $this->actingAs($admin->fresh())->get("/admin/defis/{$foreign->id}")->assertForbidden();
    }

    public function test_activities_are_limited_to_the_clubs_in_reach(): void
    {
        $leader = User::factory()->create();
        $mine = $this->club('Club Robotique');
        $other = $this->club('Club Théâtre');
        $this->holdInClub($leader, $mine);
        $challenge = $this->challenge('Défi Campus', 'university');

        $this->activity($challenge, $mine, 'Sortie Robotique');
        $this->activity($challenge, $other, 'Sortie Théâtre');

        $this->actingAs($leader->fresh())->get('/admin/activites')
            ->assertOk()
            ->assertSee('Sortie Robotique')
            ->assertDontSee('Sortie Théâtre');
    }

    public function test_scope_admin_sees_activities_of_every_club_of_their_scope_only(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');
        $one = $this->club('Club Robotique');
        $foreign = $this->club('Club Alumni Paris', 'alumni', 'regional_club');
        $challenge = $this->challenge('Défi Campus', 'university');

        $this->activity($challenge, $one, 'Sortie Robotique');
        $this->activity($challenge, $foreign, 'Sortie Alumni');

        $this->actingAs($admin->fresh())->get('/admin/activites')
            ->assertOk()
            ->assertSee('Sortie Robotique')
            ->assertDontSee('Sortie Alumni');
    }

    public function test_applications_are_limited_to_the_positions_of_the_authorized_scope(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');

        foreach (['university' => 'Poste Campus', 'alumni' => 'Poste Alumni'] as $scope => $title) {
            $position = OrgNode::query()->create([
                'relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'access_level' => 'none',
                'category' => 'direction', 'title' => $title, 'scope' => $scope, 'sort_order' => 0,
            ]);
            PositionApplication::query()->create(['node_id' => $position->id, 'user_id' => User::factory()->create()->id, 'message' => 'Candidat']);
        }

        $this->actingAs($admin->fresh())->get('/admin/structure/candidatures')
            ->assertOk()
            ->assertSee('Poste Campus')
            ->assertDontSee('Poste Alumni');
    }

    public function test_identity_verifications_are_limited_to_members_of_the_authorized_scope(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');
        $verifications = [];

        foreach (['university' => 'Membre Campus', 'alumni' => 'Membre Alumni'] as $scope => $name) {
            $user = User::factory()->create(['name' => $name]);
            Membership::query()->create(['user_id' => $user->id, 'scope' => $scope, 'status' => 'approved', 'membership_type' => 'member', 'starts_at' => '2025-10-01']);
            $verifications[$scope] = IdentityVerification::query()->create(['user_id' => $user->id, 'status' => 'pending']);
        }

        $this->actingAs($admin->fresh())->get('/admin/verifications')
            ->assertOk()
            ->assertSee('Membre Campus')
            ->assertDontSee('Membre Alumni');

        $this->actingAs($admin->fresh())->get("/admin/verifications/{$verifications['university']->id}")->assertOk();
        $this->actingAs($admin->fresh())->get("/admin/verifications/{$verifications['alumni']->id}")->assertForbidden();
        $this->actingAs($admin->fresh())->post("/admin/verifications/{$verifications['alumni']->id}/valider")->assertForbidden();
    }

    public function test_memberships_are_limited_to_the_authorized_scope(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');

        foreach (['university' => 'Adhérent Campus', 'alumni' => 'Adhérent Alumni'] as $scope => $name) {
            Membership::query()->create([
                'user_id' => User::factory()->create(['name' => $name])->id, 'scope' => $scope, 'status' => 'approved',
                'membership_type' => 'member', 'starts_at' => '2025-10-01',
            ]);
        }

        $this->actingAs($admin->fresh())->get('/admin/adhesions?mandate=all')
            ->assertOk()
            ->assertSee('Adhérent Campus')
            ->assertDontSee('Adhérent Alumni');
    }
}
