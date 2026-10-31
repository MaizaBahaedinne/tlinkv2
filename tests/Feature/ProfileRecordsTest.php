<?php

namespace Tests\Feature;

use App\Models\AcademyCourse;
use App\Models\AcademyEnrollment;
use App\Models\Governorate;
use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\ProfileOrganizations;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Profil : adhésions validées et certificats Academy affichés automatiquement, en plus des saisies externes. */
class ProfileRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function club(string $name = 'ESPRIT'): OrgNode
    {
        $this->seed(GovernorateSeeder::class);

        if (! OrgNode::query()->where('system_key', 'university-clubs')->exists()) {
            app(OrgChartInstaller::class)->install();
        }

        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();

        return app(OrgChartInstaller::class)->createClub($branch, $name, 'university_club', Governorate::query()->first(), false);
    }

    private function certificate(User $user, string $title, string $code, ?string $completedAt = '2026-06-15 10:00:00'): AcademyEnrollment
    {
        $course = AcademyCourse::query()->create(['title' => $title, 'slug' => str($title)->slug()->toString(), 'level' => 'beginner', 'status' => 'published']);

        return AcademyEnrollment::query()->create(['user_id' => $user->id, 'course_id' => $course->id, 'source' => 'free', 'enrolled_at' => now(), 'completed_at' => $completedAt, 'certificate_code' => $code]);
    }

    public function test_only_validated_non_empty_memberships_are_listed(): void
    {
        $club = $this->club('ESPRIT');
        $club->update(['logo_path' => 'clubs/logos/esprit.png']);
        $other = $this->club('ISET');
        $member = User::factory()->create();

        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved', 'membership_type' => 'member', 'starts_at' => '2025-09-15']);
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $other->id, 'status' => 'pending', 'membership_type' => 'member']);
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $other->id, 'status' => 'rejected', 'membership_type' => 'member']);
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => null, 'status' => 'approved', 'membership_type' => 'member']);

        $rows = (new ProfileOrganizations())->foundationMemberships($member->id);

        $this->assertCount(1, $rows);
        $this->assertSame('ESPRIT', $rows[0]['club_name']);
        $this->assertSame('Membre', $rows[0]['role']);
        $this->assertSame('2025-09-15', $rows[0]['start_date']);
        $this->assertNull($rows[0]['end_date']);

        $this->actingAs($member)->get('/profil')
            ->assertOk()
            ->assertSee('Adhésion validée Tunivisions')
            ->assertSee('Membre · ESPRIT', false)
            ->assertSee('/storage/clubs/logos/esprit.png', false)
            ->assertDontSee('Membre · ISET', false);

        $this->actingAs(User::factory()->create())->get('/membres/'.$member->id)->assertOk()->assertSee('Adhésion validée Tunivisions')->assertSee('ESPRIT');
    }

    public function test_accepted_mandate_participations_are_listed_with_their_position(): void
    {
        $club = $this->club('ENIT');
        $member = User::factory()->create();
        $mandate = Mandate::query()->create(['label' => 'Mandat 2023-2024', 'start_year' => 2023, 'end_year' => 2024, 'bureau_name' => 'Bureau 2023', 'status' => 'closed']);

        MandateParticipation::query()->create(['user_id' => $member->id, 'mandate_id' => $mandate->id, 'scope' => 'university', 'node_id' => $club->id, 'position_title' => 'Trésorier', 'status' => 'approved']);
        MandateParticipation::query()->create(['user_id' => $member->id, 'mandate_id' => $mandate->id, 'scope' => 'university', 'node_id' => $club->id, 'position_title' => 'Président', 'status' => 'pending']);

        $rows = (new ProfileOrganizations())->foundationMemberships($member->id);

        $this->assertCount(1, $rows);
        $this->assertSame('Trésorier', $rows[0]['role']);
        $this->assertSame('Mandat 2023-2024', $rows[0]['mandate']);

        $this->actingAs($member)->get('/profil')->assertSee('Trésorier')->assertSee('Mandat Mandat 2023-2024', false)->assertDontSee('Président ·', false);
    }

    public function test_external_experience_is_kept_and_marked_external(): void
    {
        $club = $this->club('ESPRIT');
        $member = User::factory()->create();
        Membership::query()->create(['user_id' => $member->id, 'scope' => 'university', 'node_id' => $club->id, 'status' => 'approved', 'membership_type' => 'member']);

        $this->actingAs($member)->patch('/profil', ['section' => 'associative_experiences', 'items' => [
            ['club_name' => 'Croissant Rouge', 'role' => 'Bénévole', 'start_date' => '2022-01-01'],
        ]])->assertRedirect();

        $this->actingAs($member)->get('/profil')->assertOk()->assertSee('Croissant Rouge')->assertSee('Externe')->assertSee('Adhésion validée Tunivisions');
    }

    public function test_empty_state_explains_where_records_come_from(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/profil')->assertOk()
            ->assertSee('Vos adhésions validées apparaîtront ici automatiquement')
            ->assertSee('Vos certificats Tunivisions Academy apparaîtront ici automatiquement');

        $this->actingAs(User::factory()->create())->get('/membres/'.$member->id)->assertOk()->assertDontSee('Experience associatif');
    }

    public function test_completed_academy_courses_appear_as_certificates(): void
    {
        $member = User::factory()->create();
        $this->certificate($member, 'Leadership', 'TA-ABCDE12345');
        $this->certificate($member, 'Cours Inachevé Zorglub', 'TA-ZZZZZ99999', null);

        $rows = (new ProfileOrganizations())->academyCertificates($member->id);

        $this->assertCount(1, $rows);
        $this->assertSame('Leadership', $rows[0]['name']);
        $this->assertSame('2026-06-15', $rows[0]['date_obtained']);
        $this->assertStringEndsWith('/certificats/TA-ABCDE12345', $rows[0]['url']);

        $this->actingAs($member)->get('/profil')->assertOk()
            ->assertSee('Leadership')
            ->assertSee('Obtenu via Tunivisions Academy')
            ->assertSee('/certificats/TA-ABCDE12345', false)
            ->assertDontSee('Zorglub');

        $this->actingAs(User::factory()->create())->get('/membres/'.$member->id)->assertOk()->assertSee('Leadership')->assertSee('/certificats/TA-ABCDE12345', false);
    }

    public function test_external_certifications_keep_a_safe_verification_link(): void
    {
        $member = User::factory()->create();
        $this->certificate($member, 'Leadership', 'TA-ABCDE12345');

        $this->actingAs($member)->patch('/profil', ['section' => 'certifications', 'items' => [
            ['name' => 'AWS Cloud Practitioner', 'issuer' => 'Amazon', 'date_obtained' => '2025-03-01', 'url' => 'https://aws.example/verify/42'],
        ]])->assertRedirect();

        $this->assertSame('https://aws.example/verify/42', $member->fresh()->profile->privacy_settings['certifications'][0]['url']);

        $this->actingAs($member)->get('/profil')->assertSee('AWS Cloud Practitioner')->assertSee('https://aws.example/verify/42', false)->assertSee('Leadership');

        $this->actingAs($member)->patch('/profil', ['section' => 'certifications', 'items' => [
            ['name' => 'Fausse', 'url' => 'javascript:alert(1)'],
        ]])->assertSessionHasErrors('items.0.url');
    }
}
