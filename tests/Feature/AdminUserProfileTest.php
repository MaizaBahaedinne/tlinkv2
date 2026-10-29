<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Mandate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\MemberJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AdminUserProfileTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function node(string $title, ?OrgNode $parent = null, string $relation = 'hierarchy'): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => $parent?->id,
            'relation_type' => $relation,
            'kind' => 'position',
            'capacity' => 'multiple',
            'access_level' => 'none',
            'category' => 'direction',
            'title' => $title,
            'sort_order' => 0,
        ]);
    }

    private function place(OrgNode $node, User $user, ?string $start = null, ?string $end = null): OrgAssignment
    {
        $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], ['full_name' => $user->name, 'job_title' => 'Titulaire', 'contact_visible' => true]);

        return OrgAssignment::query()->create(['node_id' => $node->id, 'person_id' => $person->id, 'starts_at' => $start, 'ends_at' => $end]);
    }

    public function test_user_page_shows_current_position_hierarchy_mandates_and_badges(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $rootNow = $this->node('Bureau 2026-2027');
        $rootOld = $this->node('Bureau 2024-2025');
        Mandate::query()->create(['label' => '2026-2027', 'start_year' => 2026, 'end_year' => 2027, 'bureau_name' => 'Bureau actuel', 'is_current' => true, 'root_node_id' => $rootNow->id]);
        Mandate::query()->create(['label' => '2024-2025', 'start_year' => 2024, 'end_year' => 2025, 'bureau_name' => 'Ancien bureau', 'is_current' => false, 'root_node_id' => $rootOld->id]);

        $president = User::factory()->create(['name' => 'Sana Presidente']);
        $member = User::factory()->create(['name' => 'Karim Membre']);
        $deputy = User::factory()->create(['name' => 'Omar Adjoint']);

        $presidency = $this->node('Présidence du club', $rootNow);
        $communication = $this->node('Responsable communication', $presidency);
        $assistant = $this->node('Assistant communication', $communication);
        $oldPost = $this->node('Trésorier sortant', $rootOld);

        $this->place($presidency, $president, '2026-09-01');
        $this->place($communication, $member, '2026-09-15');
        $this->place($assistant, $deputy, '2026-10-01');
        $this->place($oldPost, $member, '2024-09-20', '2025-06-30');

        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $presidency->id, 'status' => 'approved', 'starts_at' => '2024-10-01', 'ends_at' => '2025-06-30']);
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $presidency->id, 'status' => 'approved', 'starts_at' => '2026-10-05']);

        Profile::query()->create(['user_id' => $member->id, 'headline' => 'Étudiant en gestion', 'phone' => '22111222', 'skills' => 'Leadership, Design', 'cin' => '01234567']);

        $earned = Badge::query()->create(['slug' => 'pionnier', 'name' => 'Pionnier', 'tier' => 'gold', 'is_active' => true]);
        Badge::query()->create(['slug' => 'orateur', 'name' => 'Orateur', 'tier' => 'silver', 'is_active' => true]);
        UserBadge::query()->create(['user_id' => $member->id, 'badge_id' => $earned->id, 'awarded_at' => '2026-10-10 10:00:00']);

        $page = $this->actingAs($admin)->get('/admin/utilisateurs/'.$member->id)->assertOk();

        // Poste du mandat actuel, avec supérieur et subordonné.
        $page->assertSee('Mandat 2026-2027')
            ->assertSee('Responsable communication')
            ->assertSee('Sana Presidente')
            ->assertSee('Omar Adjoint')
            ->assertSee('Étudiant en gestion')
            ->assertSee('Leadership');

        // Parcours : le poste et l'adhésion de l'ancien mandat sont rangés sous leur mandat.
        $page->assertSee('Parcours Tunivisions')
            ->assertSeeInOrder(['Mandat 2026-2027', 'Mandat 2024-2025', 'Trésorier sortant']);

        // Badge obtenu allumé, non obtenu éteint.
        $page->assertSee('badge-tile is-earned', false)
            ->assertSee('badge-tile is-locked', false)
            ->assertSee('Obtenu le 10/10/2026')
            ->assertSee('Non obtenu');

        // Le CIN ne sort jamais.
        $page->assertDontSee('01234567');

        // Chaque adhésion et chaque poste va dans son mandat, sans doublon.
        $groups = (new MemberJourney())->byMandate($member);
        $this->assertSame(['2026-2027', '2024-2025'], $groups->pluck('mandate.label')->all());
        $this->assertSame([1, 1], $groups->map(fn ($group) => $group['positions']->count())->all());
        $this->assertSame([1, 1], $groups->map(fn ($group) => $group['memberships']->count())->all());

        $current = (new MemberJourney())->currentPositions($member);
        $this->assertSame(['Sana Presidente'], $current->first()['superiors']->pluck('name')->all());
        $this->assertSame(['Omar Adjoint'], $current->first()['subordinates']->pluck('name')->all());
    }

    public function test_user_page_works_for_a_member_without_profile_position_or_mandate(): void
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);
        $member = User::factory()->create();

        $this->actingAs($admin)->get('/admin/utilisateurs/'.$member->id)->assertOk()
            ->assertSee('Aucun poste dans le mandat actuel')
            ->assertSee('Aucun parcours')
            ->assertSee('Aucun titre renseigné');
    }
}
