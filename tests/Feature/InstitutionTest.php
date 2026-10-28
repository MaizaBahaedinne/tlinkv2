<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\Institution;
use App\Models\OrgNode;
use App\Models\Profile;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\ProfileOrganizations;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class InstitutionTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->grantPlatformAdmin($user);

        return $user;
    }

    private function club(string $name = 'Club Test', string $category = 'university_club'): OrgNode
    {
        $this->seed(GovernorateSeeder::class);

        if (! OrgNode::query()->where('system_key', 'university-clubs')->exists()) {
            app(OrgChartInstaller::class)->install();
        }

        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();

        return app(OrgChartInstaller::class)->createClub($branch, $name, $category, Governorate::query()->first(), false);
    }

    public function test_admin_manages_institutions(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/etablissements', [
            'name' => 'Université de Tunis', 'type' => 'university', 'website_url' => 'https://utunis.example',
            'is_active' => '1', 'logo' => UploadedFile::fake()->image('ut.png', 200, 100),
        ])->assertSessionHasNoErrors();

        $university = Institution::query()->where('slug', 'universite-de-tunis')->firstOrFail();
        Storage::disk('public')->assertExists($university->logo_path);

        $this->actingAs($admin)->post('/admin/etablissements', [
            'name' => 'Faculté des Sciences', 'type' => 'faculty', 'parent_id' => $university->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/admin/etablissements', ['name' => 'universite de tunis', 'type' => 'university'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->get('/admin/etablissements')->assertOk()
            ->assertSee('Université de Tunis')
            ->assertSee('Faculté des Sciences');

        $oldLogo = $university->logo_path;
        $this->actingAs($admin)->put('/admin/etablissements/'.$university->id, [
            'name' => 'Université de Tunis', 'type' => 'university', 'remove_logo' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($university->fresh()->logo_path);
        Storage::disk('public')->assertMissing($oldLogo);
    }

    public function test_a_faculty_cannot_be_its_own_parent_and_a_lycee_has_none(): void
    {
        $admin = $this->admin();
        $faculty = Institution::query()->create(['name' => 'Fac', 'slug' => 'fac', 'type' => 'university']);

        $this->actingAs($admin)->put('/admin/etablissements/'.$faculty->id, ['name' => 'Fac', 'type' => 'faculty', 'parent_id' => $faculty->id])
            ->assertSessionHasErrors('parent_id');

        $other = Institution::query()->create(['name' => 'Univ', 'slug' => 'univ', 'type' => 'university']);
        $this->actingAs($admin)->put('/admin/etablissements/'.$faculty->id, ['name' => 'Fac', 'type' => 'high_school', 'parent_id' => $other->id])
            ->assertSessionHasNoErrors();

        $this->assertNull($faculty->fresh()->parent_id);
    }

    public function test_members_cannot_manage_institutions(): void
    {
        $institution = Institution::query()->create(['name' => 'X', 'slug' => 'x', 'type' => 'other']);
        $this->actingAs(User::factory()->create());

        $this->get('/admin/etablissements')->assertForbidden();
        $this->post('/admin/etablissements', ['name' => 'Y', 'type' => 'other'])->assertForbidden();
        $this->put('/admin/etablissements/'.$institution->id, ['name' => 'Z', 'type' => 'other'])->assertForbidden();
        $this->delete('/admin/etablissements/'.$institution->id)->assertForbidden();
    }

    public function test_creating_a_university_club_creates_and_links_its_institution(): void
    {
        $club = $this->club('ESPRIT');

        $institution = Institution::query()->where('slug', 'esprit')->firstOrFail();
        $this->assertSame($institution->id, $club->fresh()->institution_id);
        $this->assertSame('university', $institution->type);
        $this->assertTrue($institution->clubs->contains('id', $club->id));
    }

    public function test_club_form_selects_an_institution_and_copies_its_name_and_type(): void
    {
        $admin = $this->admin();
        $club = $this->club('Club Robotique');
        $lycee = Institution::query()->create(['name' => 'Lycée Pilote', 'slug' => 'lycee-pilote', 'type' => 'high_school']);

        $this->actingAs($admin)->get('/admin/structure/postes/'.$club->id.'/modifier')->assertOk()->assertSee('Lycée Pilote');

        $this->actingAs($admin)->put('/admin/structure/postes/'.$club->id, [
            'title' => $club->title, 'status' => 'active', 'institution_id' => $lycee->id,
        ])->assertSessionHasNoErrors();

        $club->refresh();
        $this->assertSame($lycee->id, $club->institution_id);
        $this->assertSame('Lycée Pilote', $club->institution_name);
        $this->assertSame('high_school', $club->institution_type);

        $this->actingAs($admin)->put('/admin/structure/postes/'.$club->id, [
            'title' => $club->title, 'status' => 'active', 'institution_id' => '',
        ])->assertSessionHasNoErrors();

        $club->refresh();
        $this->assertNull($club->institution_id);
        $this->assertNull($club->institution_name);
    }

    public function test_a_faculty_is_a_university_for_its_clubs_and_renaming_propagates(): void
    {
        $institution = Institution::query()->create(['name' => 'FST', 'slug' => 'fst', 'type' => 'faculty']);
        $club = $this->club('Club FST');
        $club->update(['institution_id' => $institution->id]);

        $this->assertSame('university', $club->fresh()->institution_type);

        $institution->update(['name' => 'Faculté des Sciences de Tunis', 'type' => 'high_school']);

        $club->refresh();
        $this->assertSame('Faculté des Sciences de Tunis', $club->institution_name);
        $this->assertSame('high_school', $club->institution_type);
    }

    public function test_an_institution_with_clubs_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $club = $this->club('ISET');
        $institution = $club->fresh()->institution;

        $this->actingAs($admin)->delete('/admin/etablissements/'.$institution->id)->assertSessionHasErrors('institution');
        $this->assertNotNull(Institution::query()->find($institution->id));

        $club->update(['institution_id' => null]);
        $this->actingAs($admin)->delete('/admin/etablissements/'.$institution->id)->assertSessionHasNoErrors();
        $this->assertNull(Institution::query()->find($institution->id));
    }

    public function test_backfill_creates_institutions_from_existing_clubs_and_copies_the_logo(): void
    {
        Storage::fake('public');
        $club = $this->club('Club Informatique');
        Storage::disk('public')->put('clubs/logos/old.png', 'x');
        OrgNode::query()->whereKey($club->id)->update(['institution_id' => null, 'institution_name' => 'INSAT', 'institution_type' => 'university', 'logo_path' => 'clubs/logos/old.png']);
        Institution::query()->delete();

        Institution::backfillFromClubs();

        $institution = Institution::query()->where('slug', 'insat')->firstOrFail();
        $this->assertSame($institution->id, $club->fresh()->institution_id);
        $this->assertNotNull($institution->logo_path);
        Storage::disk('public')->assertExists($institution->logo_path);
        $this->assertNotSame('clubs/logos/old.png', $institution->logo_path);
    }

    public function test_profiles_show_the_logo_and_information_of_the_institution(): void
    {
        $this->seed(GovernorateSeeder::class);
        $university = Institution::query()->create(['name' => 'Université de Carthage', 'slug' => 'universite-de-carthage', 'type' => 'university']);
        $institution = Institution::query()->create([
            'name' => 'INSAT', 'slug' => 'insat', 'type' => 'faculty', 'parent_id' => $university->id, 'governorate_id' => Governorate::query()->firstOrFail()->id,
            'website_url' => 'https://insat.example', 'logo_path' => 'institutions/logos/insat.png',
        ]);
        $member = User::factory()->create();
        Profile::query()->create(['user_id' => $member->id, 'headline' => 'Ingénieur', 'privacy_settings' => [
            'professional_experiences' => [['company' => 'Orange', 'role' => 'Dev', 'start_date' => '2022-01-01', 'end_date' => '']],
            'educations' => [['degree_type' => 'ingenieur', 'diploma_name' => 'Informatique', 'school' => 'insat']],
        ]]);

        (new ProfileOrganizations())->backfill();

        $page = $this->actingAs(User::factory()->create())->get('/membres/'.$member->id)->assertOk();
        $page->assertSee('/storage/institutions/logos/insat.png', false)
            ->assertSee('Faculté / école supérieure · Université de Carthage', false)
            ->assertSee('https://insat.example', false)
            ->assertSee('data-highlight="school"', false)
            ->assertSee('data-highlight="company"', false);
    }

    public function test_club_logo_falls_back_to_the_institution_logo(): void
    {
        $club = $this->club('ESPRIT');
        $club->institution->update(['logo_path' => 'institutions/logos/esprit.png']);

        $this->assertNull($club->fresh()->logoUrl());
        $this->assertStringEndsWith('/storage/institutions/logos/esprit.png', (string) $club->fresh()->load('institution')->logoUrl());
    }
}
