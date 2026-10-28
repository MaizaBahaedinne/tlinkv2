<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\ProfileOrganizations;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->grantPlatformAdmin($user);

        return $user;
    }

    private function universityClub(string $name = 'ESPRIT'): OrgNode
    {
        $this->seed(GovernorateSeeder::class);

        if (! OrgNode::query()->where('system_key', 'university-clubs')->exists()) {
            app(OrgChartInstaller::class)->install();
        }

        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();

        return app(OrgChartInstaller::class)->createClub($branch, $name, 'university_club', Governorate::query()->first(), false);
    }

    /** @param array<int, array<string, string>> $items */
    private function saveSection(User $user, string $section, array $items): void
    {
        $this->actingAs($user)->patch('/profil', ['section' => $section, 'items' => $items])->assertSessionHasNoErrors();
    }

    public function test_guests_cannot_see_a_member_profile(): void
    {
        $member = User::factory()->create();

        $this->get('/membres/'.$member->id)->assertRedirect(route('login'));
    }

    public function test_a_member_profile_shows_the_public_information_but_no_contact_details(): void
    {
        $member = User::factory()->create(['name' => 'Sana Ben Ali', 'email' => 'sana@secret.test']);
        Profile::query()->create(['user_id' => $member->id, 'headline' => 'Cheffe de projet', 'bio' => 'Passionnée de robotique.', 'phone' => '99 000 111', 'address' => '12 rue Secrète', 'cin' => '01234567']);
        Post::query()->create(['user_id' => $member->id, 'type' => 'text', 'visibility' => 'authenticated', 'content' => 'Un post visible', 'comments_enabled' => true, 'published_at' => now()]);
        Post::query()->create(['user_id' => $member->id, 'type' => 'text', 'visibility' => 'restricted', 'content' => 'Un post réservé', 'comments_enabled' => true, 'published_at' => now()]);

        $this->actingAs(User::factory()->create())->get('/membres/'.$member->id)
            ->assertOk()
            ->assertSee('Sana Ben Ali')
            ->assertSee('Cheffe de projet')
            ->assertSee('Passionnée de robotique.')
            ->assertSee('Un post visible')
            ->assertDontSee('Un post réservé')
            ->assertDontSee('sana@secret.test')
            ->assertDontSee('99 000 111')
            ->assertDontSee('12 rue Secrète')
            ->assertDontSee('01234567')
            ->assertDontSee('Modifier');
    }

    public function test_viewing_your_own_profile_goes_to_the_editable_page_and_deactivated_members_are_hidden(): void
    {
        $me = User::factory()->create();
        $gone = User::factory()->create(['is_active' => false]);

        $this->actingAs($me)->get('/membres/'.$me->id)->assertRedirect(route('profile.show'));
        $this->actingAs($me)->get('/membres/'.$gone->id)->assertNotFound();
        $this->actingAs($me)->get('/membres/99999')->assertNotFound();
    }

    public function test_a_member_profile_shows_badges_clubs_and_positions(): void
    {
        $club = $this->universityClub('ESPRIT');
        $club->update(['logo_path' => 'clubs/logos/esprit.png']);
        $member = User::factory()->create();
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved']);
        $position = OrgNode::query()->create(['parent_id' => $club->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'category' => 'university_club', 'title' => 'Trésorier du club', 'sort_order' => 0]);
        $person = OrgPerson::query()->create(['user_id' => $member->id, 'full_name' => $member->name]);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $badge = \App\Models\Badge::query()->create(['slug' => 'leader', 'name' => 'Leader', 'tier' => 'gold']);
        UserBadge::query()->create(['user_id' => $member->id, 'badge_id' => $badge->id, 'awarded_at' => now()]);

        $this->actingAs(User::factory()->create())->get('/membres/'.$member->id)
            ->assertOk()
            ->assertSee('Leader')
            ->assertSee('Or')
            ->assertSee('Trésorier du club')
            ->assertSee('ESPRIT')
            ->assertSee('/storage/clubs/logos/esprit.png', false);
    }

    public function test_a_new_company_is_created_and_an_existing_one_is_selected_whatever_the_case(): void
    {
        $existing = Company::query()->create(['name' => 'Tunisie Telecom', 'slug' => 'tunisie-telecom', 'logo_path' => 'companies/logos/tt.png']);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->saveSection($first, 'professional_experiences', [
            ['company' => 'TUNISIE  telecom', 'role' => 'Ingénieur', 'start_date' => '2020-01-01'],
            ['company' => 'Startup Nouvelle', 'role' => 'CTO'],
        ]);
        $this->saveSection($second, 'professional_experiences', [['company' => 'startup nouvelle', 'role' => 'Dev']]);

        $this->assertSame(2, Company::query()->count());
        $new = Company::query()->where('slug', 'startup-nouvelle')->firstOrFail();
        $this->assertSame('Startup Nouvelle', $new->name);
        $this->assertSame($first->id, $new->created_by);

        $rows = $first->fresh()->profile->privacy_settings['professional_experiences'];
        $this->assertSame($existing->id, $rows[0]['company_id']);
        $this->assertSame($new->id, $rows[1]['company_id']);
        $this->assertSame($new->id, $second->fresh()->profile->privacy_settings['professional_experiences'][0]['company_id']);

        $this->actingAs(User::factory()->create())->get('/membres/'.$first->id)->assertSee('/storage/companies/logos/tt.png', false);
    }

    public function test_a_school_is_selected_from_the_clubs_and_never_created(): void
    {
        $club = $this->universityClub('ESPRIT');
        $club->update(['logo_path' => 'clubs/logos/esprit.png']);
        $member = User::factory()->create();

        $this->saveSection($member, 'educations', [
            ['degree_type' => 'ingenieur', 'diploma_name' => 'Génie logiciel', 'school' => 'esprit'],
            ['degree_type' => 'licence', 'diploma_name' => 'Gestion', 'school' => 'Ecole Inconnue'],
        ]);

        $rows = $member->fresh()->profile->privacy_settings['educations'];
        $this->assertSame($club->id, $rows[0]['school_id']);
        $this->assertNull($rows[1]['school_id']);
        $this->assertSame(0, Company::query()->count());

        $this->actingAs(User::factory()->create())->get('/membres/'.$member->id)
            ->assertSee('Génie logiciel')
            ->assertSee('Ecole Inconnue')
            ->assertSee('/storage/clubs/logos/esprit.png', false);

        $this->actingAs($member)->get('/profil')
            ->assertOk()
            ->assertSee('<option value="ESPRIT">', false)
            ->assertSee('/storage/clubs/logos/esprit.png', false);
    }

    public function test_an_associative_experience_is_linked_to_its_club(): void
    {
        $club = $this->universityClub('Club Robotique');
        $club->update(['logo_path' => 'clubs/logos/robot.png']);
        $member = User::factory()->create();

        $this->saveSection($member, 'associative_experiences', [['club_name' => 'club robotique', 'role' => 'Président']]);

        $this->assertSame($club->id, $member->fresh()->profile->privacy_settings['associative_experiences'][0]['club_id']);
        $this->actingAs(User::factory()->create())->get('/membres/'.$member->id)->assertSee('/storage/clubs/logos/robot.png', false);
    }

    public function test_existing_profiles_are_linked_by_the_backfill(): void
    {
        $club = $this->universityClub('ISET');
        $member = User::factory()->create();
        Profile::query()->create(['user_id' => $member->id, 'privacy_settings' => [
            'professional_experiences' => [['company' => 'Orange Tunisie', 'role' => 'Analyste']],
            'educations' => [['school' => 'ISET', 'diploma_name' => 'Licence']],
            'skill_keywords' => ['php'],
        ]]);

        (new ProfileOrganizations())->backfill();
        (new ProfileOrganizations())->backfill();

        $settings = $member->fresh()->profile->privacy_settings;
        $this->assertSame(1, Company::query()->where('slug', 'orange-tunisie')->count());
        $this->assertSame(Company::query()->value('id'), $settings['professional_experiences'][0]['company_id']);
        $this->assertSame($club->id, $settings['educations'][0]['school_id']);
        $this->assertSame(['php'], $settings['skill_keywords']);
    }

    public function test_search_links_members_to_their_profile(): void
    {
        $member = User::factory()->create(['name' => 'Sana Ben Ali']);
        $club = $this->universityClub('ESPRIT');
        $position = OrgNode::query()->create(['parent_id' => $club->id, 'relation_type' => 'hierarchy', 'kind' => 'position', 'category' => 'university_club', 'title' => 'Secrétaire', 'sort_order' => 0]);
        $person = OrgPerson::query()->create(['user_id' => $member->id, 'full_name' => 'Sana Ben Ali']);
        OrgAssignment::query()->create(['node_id' => $position->id, 'person_id' => $person->id]);

        $json = $this->actingAs(User::factory()->create())->getJson('/recherche/suggestions?q=sana')->assertOk()->json();

        $this->assertSame(route('member.show', $member->id), $json['groups'][0]['items'][0]['url']);
    }

    public function test_admin_manages_companies_and_their_logos(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $this->post('/admin/entreprises', ['name' => 'Orange Tunisie', 'website_url' => 'https://orange.tn', 'logo' => UploadedFile::fake()->image('logo.png', 200, 100)])->assertSessionHasNoErrors();
        $company = Company::query()->firstOrFail();
        Storage::disk('public')->assertExists($company->logo_path);
        $oldLogo = $company->logo_path;

        $this->post('/admin/entreprises', ['name' => 'ORANGE tunisie'])->assertSessionHasErrors('name');
        $this->assertSame(1, Company::query()->count());

        $this->get('/admin/entreprises')->assertOk()->assertSee('Orange Tunisie');

        $this->put('/admin/entreprises/'.$company->id, ['name' => 'Orange', 'logo' => UploadedFile::fake()->image('nouveau.png', 200, 100)])->assertSessionHasNoErrors();
        $company->refresh();
        $this->assertSame('orange', $company->slug);
        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertExists($company->logo_path);

        $this->put('/admin/entreprises/'.$company->id, ['name' => 'Orange', 'remove_logo' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($company->fresh()->logo_path);

        $this->post('/admin/entreprises', ['name' => 'Fausse', 'logo' => UploadedFile::fake()->create('script.php', 5, 'text/php')])->assertSessionHasErrors('logo');

        $this->delete('/admin/entreprises/'.$company->id)->assertSessionHasNoErrors();
        $this->assertSame(0, Company::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.company.created']);
    }

    public function test_non_admin_cannot_manage_companies(): void
    {
        $company = Company::query()->create(['name' => 'X', 'slug' => 'x']);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/entreprises')->assertForbidden();
        $this->post('/admin/entreprises', ['name' => 'Y'])->assertForbidden();
        $this->delete('/admin/entreprises/'.$company->id)->assertForbidden();
    }

    public function test_admin_uploads_the_logo_of_a_school_club(): void
    {
        Storage::fake('public');
        $club = $this->universityClub('ESPRIT');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/structure/postes/'.$club->id.'/modifier')->assertOk()->assertSee('Logo de l’établissement');

        $this->actingAs($admin)->put('/admin/structure/postes/'.$club->id, [
            'title' => $club->title, 'status' => 'active', 'institution_name' => 'ESPRIT',
            'logo' => UploadedFile::fake()->image('esprit.png', 200, 100),
        ])->assertSessionHasNoErrors();

        $club->refresh();
        Storage::disk('public')->assertExists($club->logo_path);
        $oldLogo = $club->logo_path;

        $this->actingAs($admin)->put('/admin/structure/postes/'.$club->id, ['title' => $club->title, 'status' => 'active', 'institution_name' => 'ESPRIT', 'remove_logo' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($club->fresh()->logo_path);
        Storage::disk('public')->assertMissing($oldLogo);
    }
}
