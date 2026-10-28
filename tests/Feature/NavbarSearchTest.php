<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class NavbarSearchTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function seedDirectory(): array
    {
        $sfax = Governorate::query()->create(['name' => 'Sfax', 'slug' => 'sfax']);
        $unit = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => 'direction', 'title' => 'Direction exécutive', 'sort_order' => 0]);
        $club = OrgNode::query()->create([
            'parent_id' => $unit->id, 'relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'university_club',
            'title' => 'Club Robotique', 'governorate_id' => $sfax->id, 'status' => 'active', 'sort_order' => 0,
        ]);
        $archived = OrgNode::query()->create([
            'parent_id' => $unit->id, 'relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'university_club',
            'title' => 'Club Robotique Archivé', 'status' => 'archived', 'sort_order' => 0,
        ]);
        $position = OrgNode::query()->create(['parent_id' => $unit->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'category' => 'direction', 'title' => 'Directrice des programmes', 'sort_order' => 0]);

        $person = OrgPerson::query()->create(['full_name' => 'Sana Ben Ali', 'job_title' => 'Directrice', 'email' => 'sana@secret.test', 'phone' => '99 000 111', 'contact_visible' => true]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);
        OrgPerson::query()->create(['full_name' => 'Sans Poste', 'job_title' => 'Orphelin']);

        $event = FoundationEvent::query()->create(['title' => 'Forum Robotique', 'location' => 'Palais des congrès', 'governorate_id' => $sfax->id, 'starts_at' => now()->addWeek(), 'status' => 'published']);
        FoundationEvent::query()->create(['title' => 'Robotique brouillon', 'starts_at' => now()->addWeek(), 'status' => 'draft']);

        return compact('club', 'archived', 'position', 'person', 'event');
    }

    public function test_guests_cannot_search(): void
    {
        $this->get('/recherche?q=robotique')->assertRedirect(route('login'));
        $this->getJson('/recherche/suggestions?q=robotique')->assertUnauthorized();
    }

    public function test_suggestions_group_people_structures_and_events_without_contact_details(): void
    {
        $data = $this->seedDirectory();
        $this->actingAs(User::factory()->create());

        $json = $this->getJson('/recherche/suggestions?q=sana')->assertOk()->json();
        $this->assertSame(['people'], array_column($json['groups'], 'key'));
        $this->assertSame('Sana Ben Ali', $json['groups'][0]['items'][0]['title']);
        $this->assertStringContainsString('bloc='.$data['position']->id, $json['groups'][0]['items'][0]['url']);

        $raw = $this->getJson('/recherche/suggestions?q=sana')->getContent();
        $this->assertStringNotContainsString('secret.test', $raw);
        $this->assertStringNotContainsString('99 000 111', $raw);

        $json = $this->getJson('/recherche/suggestions?q=robotique')->assertOk()->json();
        $this->assertSame(['structures', 'events'], array_column($json['groups'], 'key'));
        $titles = array_column($json['groups'][0]['items'], 'title');
        $this->assertSame(['Club Robotique'], $titles);
        $this->assertSame(['Forum Robotique'], array_column($json['groups'][1]['items'], 'title'));
    }

    public function test_governorate_matches_clubs_and_events(): void
    {
        $this->seedDirectory();
        $this->actingAs(User::factory()->create());

        $json = $this->getJson('/recherche/suggestions?q=sfax')->assertOk()->json();
        $this->assertSame(['structures', 'events'], array_column($json['groups'], 'key'));
    }

    public function test_people_without_a_position_and_short_terms_return_nothing(): void
    {
        $this->seedDirectory();
        $this->actingAs(User::factory()->create());

        $this->assertSame([], $this->getJson('/recherche/suggestions?q=orphelin')->json('groups'));
        $this->assertSame([], $this->getJson('/recherche/suggestions?q=s')->json('groups'));
        $this->getJson('/recherche/suggestions?q='.str_repeat('a', 81))->assertUnprocessable();
    }

    public function test_like_wildcards_are_escaped(): void
    {
        $this->seedDirectory();
        $this->actingAs(User::factory()->create());

        $this->assertSame([], $this->getJson('/recherche/suggestions?q=%25%25')->json('groups'));
    }

    public function test_results_page_lists_matches(): void
    {
        $this->seedDirectory();
        $this->actingAs(User::factory()->create(['onboarding_completed_at' => now()]));

        $this->get('/recherche?q=robotique')->assertOk()
            ->assertSee('Résultats pour', false)->assertSee('Club Robotique')->assertSee('Forum Robotique')
            ->assertDontSee('Robotique brouillon')->assertDontSee('Archivé');

        $this->get('/recherche?q=zzzzzz')->assertOk()->assertSee('Aucun résultat');
        $this->get('/recherche')->assertOk()->assertSee('au moins 2 caractères');
    }

    public function test_navbar_shows_navigation_buttons_search_and_unread_badge(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        AppNotification::query()->create(['user_id' => $user->id, 'type' => 'x', 'title' => 'A', 'body' => 'b']);
        AppNotification::query()->create(['user_id' => $user->id, 'type' => 'x', 'title' => 'B', 'body' => 'b', 'read_at' => now()]);

        foreach (['/feed', '/profil', '/notifications'] as $page) {

            $this->actingAs($user)->get($page)->assertOk()
                ->assertSee('id="nav-search"', false)
                ->assertSee('Navigation de l\'application', false)
                ->assertSee(route('orgchart.index'), false)
                ->assertSee('1 non lues', false);
        }

        $this->actingAs($user)->get('/feed')->assertDontSee(route('admin.dashboard'), false);
    }

    public function test_administration_button_is_only_for_admins(): void
    {
        $admin = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->get('/profil')->assertOk()->assertSee(route('admin.dashboard'), false);
    }
}
