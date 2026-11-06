<?php

namespace Tests\Feature;

use App\Models\AppModule;
use App\Models\AppNotification;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\Membership;
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

    public function test_only_positions_are_listed_and_the_tree_shows_each_level(): void
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

        $this->assertLessThan(strpos($html, 'Adjoint Profond'), strpos($html, 'Chef Premier'), 'un chef passe avant son adjoint dans l\'arbre');
        $this->assertLessThan(strpos($html, 'Chef Second'), strpos($html, 'Adjoint Profond'), 'l\'arbre se lit branche par branche');
        $this->assertStringContainsString('class="org-tree', $html);
    }

    public function test_the_team_is_drawn_as_a_tree_where_each_position_sits_under_its_own_parent(): void
    {
        $first = $this->node('Chef Premier', $this->top);
        $deputy = $this->node('Adjoint Profond', $first);
        $second = $this->node('Chef Second', $this->top);
        $first->update(['sort_order' => 1]);
        $second->update(['sort_order' => 2]);

        $html = $this->actingAs($this->place($this->top, 'Karim Directeur'))->get('/mon-bureau')->assertOk()->getContent();

        $tree = substr($html, strpos($html, 'class="org-tree'));
        $firstBranch = substr($tree, strpos($tree, 'Chef Premier'), strpos($tree, 'Chef Second') - strpos($tree, 'Chef Premier'));

        $this->assertStringContainsString('Adjoint Profond', $firstBranch, 'l\'adjoint est dans la branche de son chef');
        $this->assertStringNotContainsString('Adjoint Profond', substr($tree, strpos($tree, 'Chef Second')));
    }

    public function test_superiors_are_drawn_above_my_position_in_the_tree(): void
    {
        $this->place($this->top, 'Karim Directeur');
        $middle = $this->place($this->middle, 'Sonia Cheffe');

        $html = $this->actingAs($middle)->get('/mon-bureau')->assertOk()->assertSee('Niveau +1')->getContent();

        $tree = substr($html, strpos($html, '<ul>', strpos($html, 'class="org-tree')));
        $this->assertLessThan(strpos($tree, 'Chef de pôle'), strpos($tree, 'Directeur du bureau'));
        $this->assertLessThan(strpos($tree, 'Assistant de pôle'), strpos($tree, 'Chef de pôle'));
    }

    public function test_activities_the_member_took_part_in_are_listed(): void
    {
        $club = OrgNode::query()->create(['kind' => 'club', 'title' => 'Club Robotique Sfax', 'status' => 'active', 'category' => 'university_club', 'scope' => 'university']);
        $challenge = \App\Models\Challenge::query()->create([
            'title' => 'Défi Environnement', 'theme' => 'Sensibilisation', 'scope' => 'university',
            'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonth(), 'evaluation_at' => now()->addMonths(2), 'results_at' => now()->addMonths(3),
            'status' => 'published',
        ]);
        $make = fn (string $title, $start, string $status = 'published') => \App\Models\ClubActivity::query()->create([
            'challenge_id' => $challenge->id, 'club_id' => $club->id, 'title' => $title, 'type' => 'event', 'audience' => 'public',
            'in_theme' => true, 'starts_at' => $start, 'status' => $status,
        ]);

        $attended = $make('Nettoyage de plage', now()->subDays(10));
        $missed = $make('Atelier sans présence', now()->subDays(8));
        $comingUp = $make('Hackathon à venir', now()->addDays(5));
        $cancelled = $make('Sortie annulée', now()->subDays(6), 'cancelled');
        $foreign = $make('Activité d\'un autre membre', now()->subDays(4));

        $member = User::factory()->create();
        $other = User::factory()->create();
        $register = fn ($activity, $user, array $extra = []) => \App\Models\ActivityRegistration::query()->create(['activity_id' => $activity->id, 'user_id' => $user->id, 'status' => 'registered'] + $extra);
        $register($attended, $member, ['checked_in_at' => now()->subDays(10)]);
        $register($missed, $member);
        $register($comingUp, $member);
        $register($cancelled, $member, ['checked_in_at' => now()->subDays(6)]);
        $register($foreign, $other, ['checked_in_at' => now()->subDays(4)]);

        $html = $this->actingAs($member)->get('/mon-bureau')->assertOk()
            ->assertSee('Activités des clubs auxquelles j’ai participé ou je suis inscrit(e)')
            ->assertSee('Nettoyage de plage')
            ->assertSee('Atelier sans présence')
            ->assertSee('Hackathon à venir')
            ->assertDontSee('Sortie annulée')
            ->assertDontSee('Activité d\'un autre membre', false)
            ->assertDontSee('Activité · Nettoyage de plage')
            ->getContent();

        $this->assertStringContainsString('Présent(e)', $html);
        $this->assertStringContainsString('Inscrit(e)', $html);

        // À venir et passées sont repliées, affichées sur demande.
        $section = substr($html, strpos($html, 'id="activites"'));
        $this->assertSame(2, substr_count($section, '<details'));
        $this->assertStringNotContainsString('<details open', $section);
        $this->assertLessThan(strpos($section, 'Atelier sans présence'), strpos($section, 'Hackathon à venir'));
    }

    public function test_the_clubs_button_is_gone_from_the_navigation_bar(): void
    {
        $html = $this->actingAs($this->place($this->middle, 'Sonia Cheffe'))->get('/mon-bureau')->assertOk()
            ->assertDontSee('title="Clubs"', false)
            ->assertDontSee('aria-label="Clubs"', false)
            ->getContent();

        $this->assertStringNotContainsString('fa-people-roof', $html);
        $this->actingAs(User::factory()->create())->get('/clubs')->assertOk();
    }

    public function test_avatars_are_shown_and_phone_numbers_are_hidden(): void
    {
        $director = $this->place($this->top, 'Karim Directeur');
        $director->profile()->create(['phone' => '+216 20 111 222', 'photo_path' => 'profiles/avatars/karim.jpg']);
        $middle = $this->place($this->middle, 'Sonia Cheffe');
        $middle->profile()->create(['phone' => '+216 99 888 777']);
        $assistant = $this->place($this->low, 'Yassine Assistant');
        $assistant->profile()->create(['phone' => '+216 55 444 333']);

        $this->actingAs($middle)->get('/mon-bureau')->assertOk()
            ->assertSee('/storage/profiles/avatars/karim.jpg', false)
            ->assertSee('>YA<', false)
            ->assertDontSee('+216 20 111 222')
            ->assertDontSee('+216 55 444 333')
            ->assertDontSee('20 111 222');
    }

    public function test_club_offices_below_me_are_hidden(): void
    {
        $club = $this->node('Club Robotique', $this->top);
        $club->update(['kind' => 'club']);
        $clubDept = $this->node('Département Média du club', $club);
        $clubDept->update(['kind' => 'club_department']);
        $this->node('Président du club', $club);
        $this->node('Responsable média', $clubDept);
        $this->node('Chargé de communication nationale', $this->top);

        $this->actingAs($this->place($this->top, 'Karim Directeur'))->get('/mon-bureau')->assertOk()
            ->assertSee('Chargé de communication nationale')
            ->assertDontSee('Président du club')
            ->assertDontSee('Responsable média')
            ->assertDontSee('Club Robotique');
    }

    public function test_a_club_president_still_sees_their_own_club_team(): void
    {
        $club = $this->node('Club Robotique', $this->top);
        $club->update(['kind' => 'club']);
        $president = $this->node('Président du club', $club);
        $dept = $this->node('Département Média du club', $president);
        $dept->update(['kind' => 'club_department']);
        $this->node('Responsable média', $dept);
        $this->node('Trésorier du club', $president);

        $this->actingAs($this->place($president, 'Nour Présidente'))->get('/mon-bureau')->assertOk()
            ->assertSee('Trésorier du club')
            ->assertSee('Responsable média');
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

    public function test_the_page_is_a_single_clear_layout_without_tabs_or_the_header_buttons(): void
    {
        $admin = $this->place($this->top, 'Karim Directeur');

        $html = $this->actingAs($admin)->get('/mon-bureau')->assertOk()
            ->assertDontSee('role="tablist"', false)
            ->assertSee('id="activites"', false)
            ->assertDontSee('Mes adhésions en cours')
            ->assertDontSee('Mes mandats en cours')
            ->assertSee('id="postes"', false)
            ->assertSee('id="clubs"', false)
            ->assertSee('id="evenements"', false)
            ->assertSee('id="parcours"', false)
            ->assertSee('href="#postes"', false)
            ->assertDontSee('>Retour<', false)
            ->getContent();

        // La navigation de l'application (barre du bas sur mobile) affiche « Administration » : seul le contenu de la page compte ici.
        $html = preg_replace('#<nav\b.*?</nav>#s', '', $html);

        $this->assertStringNotContainsString('>Organigramme<', $html);
        $this->assertStringNotContainsString('>Administration<', $html);
    }

    public function test_the_menu_link_only_shows_for_position_holders(): void
    {
        $holder = $this->place($this->middle, 'Sonia Cheffe');
        $other = User::factory()->create();

        $this->actingAs($holder)->get('/profil')->assertOk()->assertSee('/mon-bureau', false);
        $this->actingAs($other)->get('/profil')->assertOk()->assertDontSee('/mon-bureau', false);
        $this->actingAs($other)->get('/mon-bureau')->assertOk()->assertSee('aucun poste');
    }

    public function test_members_without_a_position_see_their_membership_club_events_and_journey(): void
    {
        $club = OrgNode::query()->create(['kind' => 'club', 'title' => 'Club Robotique Sfax', 'status' => 'active', 'category' => 'university_club', 'scope' => 'university']);
        $department = OrgNode::query()->create(['parent_id' => $club->id, 'kind' => 'club_department', 'title' => 'Pôle Média', 'category' => 'university_club']);
        $president = $this->node('Présidente du club', $club);
        $this->node('Responsable média', $department);
        $this->place($president, 'Amel Présidente');

        $member = User::factory()->create(['name' => 'Yosra Membre']);
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved', 'starts_at' => now()->subYear()]);
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'high-school', 'status' => 'archived', 'starts_at' => now()->subYears(4), 'ends_at' => now()->subYears(2)]);

        $coming = FoundationEvent::query()->create(['title' => 'Hackathon du club', 'status' => 'published', 'node_id' => $club->id, 'starts_at' => now()->addDays(10)]);
        $gone = FoundationEvent::query()->create(['title' => 'Journée portes ouvertes', 'status' => 'completed', 'node_id' => $club->id, 'starts_at' => now()->subDays(20)]);
        $foreign = FoundationEvent::query()->create(['title' => 'Salon d\'un autre club', 'status' => 'published', 'starts_at' => now()->addDays(3)]);
        $draft = FoundationEvent::query()->create(['title' => 'Brouillon du club', 'status' => 'draft', 'node_id' => $club->id, 'starts_at' => now()->addDays(4)]);
        EventRegistration::query()->create(['event_id' => $gone->id, 'user_id' => $member->id, 'status' => 'attended', 'registered_at' => now()->subDays(25)]);

        $this->actingAs($member)->get('/profil')->assertOk()->assertSee('/mon-bureau', false);

        $this->actingAs($member)->get('/mon-bureau')->assertOk()
            ->assertDontSee('Mes adhésions en cours')
            ->assertDontSee('Aucun mandat en cours.')
            ->assertSee('Mes clubs')
            ->assertSee('Club Robotique Sfax')
            ->assertSee('Hiérarchie du club')
            ->assertSee('Pôle Média')
            ->assertSee('Présidente du club')
            ->assertSee('Amel Présidente')
            ->assertSee('Responsable média')
            ->assertSee('Hackathon du club')
            ->assertSee('Journée portes ouvertes')
            ->assertSee('Présent(e)')
            ->assertSee('Mon parcours')
            ->assertSee('Adhésion · Club Robotique Sfax')
            ->assertDontSee('Événement · Journée portes ouvertes')
            ->assertDontSee('Événement · Hackathon du club')
            ->assertDontSee('Salon d\'un autre club', false)
            ->assertDontSee('Brouillon du club');
    }

    public function test_the_journey_keeps_ended_mandates(): void
    {
        $user = $this->place($this->low, 'Yassine Assistant');
        $past = $this->node('Ancien poste de trésorier', $this->top);
        $person = OrgPerson::query()->where('user_id', $user->id)->firstOrFail();
        OrgAssignment::query()->create(['node_id' => $past->id, 'person_id' => $person->id, 'starts_at' => now()->subYears(3), 'ends_at' => now()->subYear()]);

        $this->actingAs($user)->get('/mon-bureau')->assertOk()
            ->assertSee('Mandat · Assistant de pôle')
            ->assertSee('Mandat · Ancien poste de trésorier')
            ->assertSee('Terminé')
            ->assertDontSee('Mes mandats en cours');
    }
}
