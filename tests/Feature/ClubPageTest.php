<?php

namespace Tests\Feature;

use App\Models\ClubNews;
use App\Models\FoundationEvent;
use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\ClubPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ClubPageTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function member(string $name = 'Membre Test'): User
    {
        return User::factory()->create(['name' => $name, 'onboarding_completed_at' => now()]);
    }

    private function club(array $attributes = []): OrgNode
    {
        return OrgNode::query()->create($attributes + [
            'relation_type' => 'hierarchy', 'kind' => 'club', 'category' => 'university_club', 'title' => 'Club Robotique',
            'scope' => 'university', 'status' => 'active', 'sort_order' => 0,
        ]);
    }

    private function position(OrgNode $parent, string $title, string $capacity = 'single', string $level = 'none', string $kind = 'position'): OrgNode
    {
        return OrgNode::query()->create([
            'parent_id' => $parent->id, 'relation_type' => 'hierarchy', 'kind' => $kind, 'capacity' => $capacity, 'access_level' => $level,
            'category' => 'university_club', 'title' => $title, 'sort_order' => 0,
        ]);
    }

    private function place(OrgNode $position, User $user, ?string $from = null, ?string $to = null): void
    {
        $person = OrgPerson::query()->create(['user_id' => $user->id, 'full_name' => $user->name]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id, 'starts_at' => $from, 'ends_at' => $to]);
    }

    private function mandate(int $year, string $status = 'closed'): Mandate
    {
        return Mandate::query()->create(['label' => $year.'-'.($year + 1), 'start_year' => $year, 'end_year' => $year + 1, 'bureau_name' => 'Bureau '.$year, 'status' => $status]);
    }

    public function test_the_pages_require_a_login_and_only_show_visible_clubs(): void
    {
        $club = $this->club();
        $draft = $this->club(['title' => 'Club Brouillon', 'status' => 'draft']);
        $unit = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => 'national_office', 'title' => 'Bureau', 'sort_order' => 0]);

        $this->get('/clubs')->assertRedirect('/connexion');
        $this->get('/clubs/'.$club->id)->assertRedirect('/connexion');

        $user = $this->member();
        $this->actingAs($user)->get('/clubs/'.$club->id)->assertOk();
        $this->actingAs($user)->get('/clubs/'.$draft->id)->assertNotFound();
        $this->actingAs($user)->get('/clubs/'.$unit->id)->assertNotFound();
        $this->actingAs($user)->get('/clubs/'.$unit->id.'/onglets/membres')->assertNotFound();
        $this->actingAs($user)->get('/clubs/'.$club->id.'/onglets/inconnu')->assertNotFound();
    }

    public function test_the_directory_lists_active_clubs_and_filters_them(): void
    {
        $this->club(['title' => 'Club Alpha']);
        $this->club(['title' => 'Club Beta', 'scope' => 'high-school']);
        $this->club(['title' => 'Club Inactif', 'status' => 'inactive']);
        $user = $this->member();

        $this->actingAs($user)->get('/clubs')->assertOk()->assertSee('Club Alpha')->assertSee('Club Beta')->assertDontSee('Club Inactif');
        $this->actingAs($user)->get('/clubs?q=Alpha')->assertOk()->assertSee('Club Alpha')->assertDontSee('Club Beta');
        $this->actingAs($user)->get('/clubs?scope=high-school')->assertOk()->assertSee('Club Beta')->assertDontSee('Club Alpha');
    }

    public function test_the_hero_shows_identity_and_only_the_requested_tab(): void
    {
        $club = $this->club([
            'founded_on' => now()->subYears(4)->toDateString(), 'address' => 'Campus El Manar', 'description' => 'Un club de robotique ouvert à tous.',
            'website_url' => 'https://robotique.example.org', 'instagram_url' => 'javascript:alert(1)', 'charter_signed' => true,
        ]);
        $user = $this->member();

        $this->actingAs($user)->get('/clubs/'.$club->id)
            ->assertOk()
            ->assertSee('Club Robotique')
            ->assertSee('Fondé en '.now()->subYears(4)->year)
            ->assertSee('4 ans')
            ->assertSee('Campus El Manar')
            ->assertSee('Charte signée')
            ->assertSee('Un club de robotique ouvert à tous.')
            ->assertSee('https://robotique.example.org')
            ->assertDontSee('javascript:alert(1)')
            ->assertDontSee('Bureau actuel');

        $this->actingAs($user)->get('/clubs/'.$club->id.'?onglet=bureaux')->assertOk()->assertSee('Bureau actuel')->assertDontSee('Un club de robotique ouvert à tous.');
    }

    public function test_every_tab_is_available_as_a_fragment_without_the_page_layout(): void
    {
        $club = $this->club();
        $user = $this->member();

        foreach (array_keys(ClubPage::TABS) as $tab) {
            $response = $this->actingAs($user)->get('/clubs/'.$club->id.'/onglets/'.$tab);
            $response->assertOk();
            $this->assertStringNotContainsString('<html', $response->getContent(), $tab);
        }
    }

    public function test_members_are_listed_by_department_in_hierarchy_order(): void
    {
        $club = $this->club();
        $president = $this->position($club, 'Président de club', 'single', 'club_president');
        $department = $this->position($president, 'Département Marketing', 'single', 'none', 'club_department');
        $vp = $this->position($department, 'VP Marketing');
        $team = $this->position($department, 'Membres Marketing', 'multiple');
        $this->place($president, $this->member('Sana Président'));
        $this->place($vp, $this->member('Karim Vice'));
        $this->place($team, $this->member('Lina Équipe'));
        $inactive = $this->member('Compte Inactif');
        $inactive->forceFill(['is_active' => false])->save();
        $this->place($team, $inactive);
        $adherent = $this->member('Adhérent Simple');
        Membership::query()->create(['user_id' => $adherent->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved']);

        $html = $this->actingAs($this->member())->get('/clubs/'.$club->id.'/onglets/membres')->assertOk()->getContent();

        $this->assertStringContainsString('Direction du club', $html);
        $this->assertStringContainsString('Département Marketing', $html);
        $this->assertStringContainsString('Adhérents', $html);
        $this->assertStringNotContainsString('Compte Inactif', $html);
        $this->assertLessThan(strpos($html, 'Département Marketing'), strpos($html, 'Sana Président'));
        $this->assertLessThan(strpos($html, 'Lina Équipe'), strpos($html, 'Karim Vice'));
        $this->assertStringContainsString('Adhérent Simple', $html);
    }

    public function test_the_bureau_tab_shows_the_current_bureau_the_history_by_mandate_and_former_presidents(): void
    {
        $this->mandate(2021);
        $this->mandate(2022);
        $this->mandate(2025, 'current');
        $club = $this->club();
        $president = $this->position($club, 'Président de club', 'single', 'club_president');
        $treasurer = $this->position($club, 'Trésorier');

        $this->place($president, $this->member('Ancien Président'), '2021-09-01', '2022-08-31');
        $this->place($president, $this->member('Présidente Actuelle'), '2025-09-01');
        $this->place($treasurer, $this->member('Trésorier Actuel'), null, null);

        $declared = $this->member('Déclaré Président');
        MandateParticipation::query()->create([
            'user_id' => $declared->id, 'mandate_id' => Mandate::query()->where('start_year', 2022)->value('id'), 'scope' => 'university',
            'node_id' => $club->id, 'position_title' => 'Président de club', 'status' => 'approved',
        ]);
        MandateParticipation::query()->create([
            'user_id' => $this->member('Refusé')->id, 'mandate_id' => Mandate::query()->where('start_year', 2022)->value('id'), 'scope' => 'university',
            'node_id' => $club->id, 'position_title' => 'Président de club', 'status' => 'pending',
        ]);

        $html = $this->actingAs($this->member())->get('/clubs/'.$club->id.'/onglets/bureaux')->assertOk()->getContent();

        $this->assertStringContainsString('Bureau actuel', $html);
        $this->assertStringContainsString('mandat 2025-2026', $html);
        $this->assertStringContainsString('Présidente Actuelle', $html);
        $this->assertStringContainsString('Trésorier Actuel', $html);
        $this->assertStringContainsString('Anciens présidents', $html);
        $this->assertStringContainsString('Mandat 2021-2022', $html);
        $this->assertStringContainsString('Mandat 2022-2023', $html);
        $this->assertStringContainsString('Ancien Président', $html);
        $this->assertStringContainsString('Déclaré Président', $html);
        $this->assertStringContainsString('Déclaré par le membre', $html);
        $this->assertStringNotContainsString('Refusé', $html);
    }

    public function test_the_organisation_tab_shows_the_hierarchy_and_neighbour_clubs(): void
    {
        $unit = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'unit', 'category' => 'national_office', 'title' => 'Branche Université', 'sort_order' => 0]);
        $club = $this->club(['parent_id' => $unit->id]);
        $this->club(['title' => 'Club Voisin', 'parent_id' => $unit->id]);
        $this->club(['title' => 'Club Rattaché', 'parent_id' => $club->id]);
        $this->position($club, 'Département Média', 'single', 'none', 'club_department');

        $this->actingAs($this->member())->get('/clubs/'.$club->id.'/onglets/organisation')
            ->assertOk()
            ->assertSee('Branche Université')
            ->assertSee('Club Voisin')
            ->assertSee('Club Rattaché')
            ->assertSee('Département Média');
    }

    public function test_the_activity_tab_lists_published_events_of_the_club_only(): void
    {
        $club = $this->club();
        $other = $this->club(['title' => 'Autre Club']);
        FoundationEvent::query()->create(['title' => 'Hackathon à venir', 'node_id' => $club->id, 'status' => 'published', 'starts_at' => now()->addDays(10)]);
        FoundationEvent::query()->create(['title' => 'Atelier passé', 'node_id' => $club->id, 'status' => 'completed', 'starts_at' => now()->subDays(10)]);
        FoundationEvent::query()->create(['title' => 'Événement brouillon', 'node_id' => $club->id, 'status' => 'draft', 'starts_at' => now()->addDays(3)]);
        FoundationEvent::query()->create(['title' => 'Événement ailleurs', 'node_id' => $other->id, 'status' => 'published', 'starts_at' => now()->addDays(3)]);

        $html = $this->actingAs($this->member())->get('/clubs/'.$club->id.'/onglets/activites')->assertOk()->getContent();

        $this->assertStringContainsString('Hackathon à venir', $html);
        $this->assertStringContainsString('Atelier passé', $html);
        $this->assertStringNotContainsString('Événement brouillon', $html);
        $this->assertStringNotContainsString('Événement ailleurs', $html);
    }

    public function test_a_club_president_publishes_and_deletes_news_but_members_cannot(): void
    {
        Storage::fake('public');
        $club = $this->club();
        $president = $this->position($club, 'Président de club', 'single', 'club_president');
        $boss = $this->member('Présidente Club');
        $this->place($president, $boss);
        $simple = $this->member('Simple Membre');

        $this->actingAs($simple)->post('/clubs/'.$club->id.'/actualites', ['title' => 'Piratage', 'body' => 'Non'])->assertForbidden();
        $this->assertSame(0, ClubNews::query()->count());
        $this->actingAs($simple)->get('/clubs/'.$club->id.'/onglets/actualites')->assertOk()->assertDontSee('Publier une actualité');

        $this->actingAs($boss)->get('/clubs/'.$club->id.'/onglets/actualites')->assertOk()->assertSee('Publier une actualité');
        $this->actingAs($boss)->post('/clubs/'.$club->id.'/actualites', [
            'title' => 'Assemblée générale', 'body' => "Rendez-vous samedi.\nVenez nombreux.", 'cover' => UploadedFile::fake()->image('une.jpg', 600, 400),
        ])->assertRedirect(route('club.show', [$club, 'onglet' => 'actualites']));

        $news = ClubNews::query()->firstOrFail();
        $this->assertSame($club->id, $news->club_id);
        $this->assertSame($boss->id, $news->user_id);
        Storage::disk('public')->assertExists($news->cover_path);

        $this->actingAs($simple)->get('/clubs/'.$club->id.'/onglets/actualites')->assertOk()->assertSee('Assemblée générale')->assertSee('Venez nombreux.');
        $this->actingAs($simple)->delete('/clubs/'.$club->id.'/actualites/'.$news->id)->assertForbidden();

        $this->actingAs($boss)->post('/clubs/'.$club->id.'/actualites', ['title' => '', 'body' => 'x'])->assertSessionHasErrors('title');

        $coverPath = $news->cover_path;
        $this->actingAs($boss)->delete('/clubs/'.$club->id.'/actualites/'.$news->id)->assertRedirect();
        $this->assertSame(0, ClubNews::query()->count());
        Storage::disk('public')->assertMissing($coverPath);
    }

    public function test_news_of_another_club_cannot_be_deleted_through_this_club(): void
    {
        $club = $this->club();
        $other = $this->club(['title' => 'Autre Club']);
        $president = $this->position($club, 'Président de club', 'single', 'club_president');
        $boss = $this->member();
        $this->place($president, $boss);
        $news = ClubNews::query()->create(['club_id' => $other->id, 'title' => 'Chez les autres', 'body' => 'Texte', 'published_at' => now()]);

        $this->actingAs($boss)->delete('/clubs/'.$club->id.'/actualites/'.$news->id)->assertForbidden();
        $this->assertSame(1, ClubNews::query()->count());
    }

    public function test_future_news_stays_hidden_until_its_publication_date(): void
    {
        $club = $this->club();
        ClubNews::query()->create(['club_id' => $club->id, 'title' => 'Annonce programmée', 'body' => 'Texte', 'published_at' => now()->addDay()]);

        $this->actingAs($this->member())->get('/clubs/'.$club->id.'/onglets/actualites')->assertOk()->assertDontSee('Annonce programmée');
    }

    public function test_the_admin_club_form_saves_the_cover_location_and_links(): void
    {
        Storage::fake('public');
        $club = $this->club();

        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        $this->actingAs($admin)->put('/admin/structure/postes/'.$club->id, [
            'title' => $club->title, 'status' => 'active',
            'address' => 'Technopole de Sfax', 'website_url' => 'https://club.example.org', 'instagram_url' => 'javascript:alert(1)',
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 400),
        ])->assertSessionHasErrors('instagram_url');

        $this->actingAs($admin)->put('/admin/structure/postes/'.$club->id, [
            'title' => $club->title, 'status' => 'active',
            'address' => 'Technopole de Sfax', 'website_url' => 'https://club.example.org',
            'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 400),
        ])->assertSessionHasNoErrors();

        $club->refresh();
        $this->assertSame('Technopole de Sfax', $club->address);
        $this->assertSame('https://club.example.org', $club->website_url);
        $this->assertNotNull($club->cover_path);
        Storage::disk('public')->assertExists($club->cover_path);
    }
}
