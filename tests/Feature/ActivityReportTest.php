<?php

namespace Tests\Feature;

use App\Models\ActivityReport;
use App\Models\AppNotification;
use App\Models\Challenge;
use App\Models\ClubActivity;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Compte rendu de clôture d'une activité : rédaction par le club, validation du président, évaluation par le comité. */
class ActivityReportTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private OrgNode $club;

    private ClubActivity $activity;

    private User $president;

    private User $writer;

    private User $member;

    private User $juror;

    private \App\Models\ChallengeCriterion $criterion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernorateSeeder::class);
        app(OrgChartInstaller::class)->install();
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $this->club = app(OrgChartInstaller::class)->createClub($branch, 'ESPRIT', 'university_club', Governorate::query()->first(), true);

        $this->president = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $this->club->title)->where('role_key', 'president')->firstOrFail(), $this->president);

        // Rédacteur : responsable des clubs du périmètre, mais pas président.
        $this->writer = User::factory()->create();
        $this->grantAccess($this->writer, 'manager', 'university');

        $this->member = User::factory()->create(['name' => 'Salma Ben Ali']);
        Membership::query()->create(['user_id' => $this->member->id, 'scope' => 'university', 'node_id' => $this->club->id, 'status' => 'approved', 'membership_type' => 'member']);

        $this->juror = User::factory()->create();

        $challenge = Challenge::query()->create([
            'title' => 'Défi Environnement', 'theme' => 'Écologie', 'scope' => 'university', 'status' => 'published',
            'starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(5), 'evaluation_at' => now()->addDays(7), 'results_at' => now()->addDays(9),
        ]);
        $this->criterion = $challenge->criteria()->create(['kind' => 'theme', 'label' => 'Impact', 'max_points' => 10, 'sort_order' => 0]);
        $challenge->committee()->attach($this->juror->id);

        $this->activity = ClubActivity::query()->create([
            'challenge_id' => $challenge->id, 'club_id' => $this->club->id, 'title' => 'Nettoyage de plage', 'type' => 'event', 'audience' => 'public',
            'in_theme' => true, 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(3), 'status' => 'published',
        ]);
    }

    private function complete(array $overrides = []): array
    {
        return $overrides + [
            'action' => 'submit',
            'summary' => 'Quarante bénévoles ont nettoyé la plage.',
            'hr_summary' => 'Équipe très motivée.',
            'members' => [['user_id' => $this->member->id, 'role' => 'Logistique', 'implication' => 4, 'comment' => 'Très présente']],
            'proof_url' => 'https://www.facebook.com/esprit/posts/1',
            'poster_url' => 'https://www.facebook.com/esprit/posts/2',
            'self' => [$this->criterion->id => ['points' => '7.5', 'comment' => 'Bon impact local']],
            'self_strengths' => 'Belle mobilisation', 'self_improvements' => 'Plus de partenaires',
            'media' => [['kind' => 'facebook', 'label' => 'Page du club', 'url' => 'https://facebook.com/esprit/posts/1'], ['kind' => 'radio', 'label' => 'Radio Campus, 12/11']],
            'partners' => [['name' => 'Banque Zitouna', 'nature' => 'sponsor', 'amount' => '1500', 'description' => 'Sponsor principal']],
            'budget_planned' => '2000', 'budget_spent' => '1800', 'budget_income' => '300', 'budget_notes' => 'Équilibré',
        ];
    }

    public function test_report_is_only_written_once_the_activity_is_over(): void
    {
        $future = ClubActivity::query()->create([
            'challenge_id' => $this->activity->challenge_id, 'club_id' => $this->club->id, 'title' => 'À venir', 'type' => 'event', 'audience' => 'public',
            'in_theme' => true, 'starts_at' => now()->addDay(), 'status' => 'published',
        ]);

        $this->actingAs($this->president)->get("/admin/activites/{$future->id}/compte-rendu")->assertRedirect(route('admin.activities.show', $future));
        $this->actingAs($this->president)->put("/admin/activites/{$future->id}/compte-rendu", $this->complete())->assertStatus(422);
        $this->assertSame(0, ActivityReport::query()->count());

        $this->actingAs($this->president)->get("/admin/activites/{$this->activity->id}/compte-rendu")->assertOk()->assertSee('Ressources humaines')->assertSee('Salma Ben Ali');
    }

    public function test_submission_requires_every_part_but_a_draft_does_not(): void
    {
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";

        $this->actingAs($this->writer)->put($url, ['action' => 'submit'])->assertSessionHasErrors(['summary', 'members', 'proof_url', 'self', 'budget_spent']);
        $this->assertSame(0, ActivityReport::query()->count());

        $this->actingAs($this->writer)->put($url, ['action' => 'draft', 'summary' => 'En cours'])->assertSessionHasNoErrors();
        $report = ActivityReport::query()->firstOrFail();
        $this->assertSame('draft', $report->status);
        $this->assertSame('En cours', $report->summary);
    }

    public function test_club_submits_the_report_and_the_president_validates_it_for_the_committee(): void
    {
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";

        $this->actingAs($this->writer)->put($url, $this->complete())->assertRedirect($url)->assertSessionHas('status');

        $report = ActivityReport::query()->firstOrFail();
        $this->assertSame('pending', $report->status);
        $this->assertSame($this->writer->id, $report->submitted_by);
        $this->assertSame(1, $report->members()->count());
        $this->assertSame(4, $report->members()->first()->implication);
        $this->assertSame(1500.0, $report->partners()->first()->amount);
        $this->assertSame(['facebook', 'radio'], $report->items()->whereIn('kind', ['facebook', 'radio'])->orderBy('id')->pluck('kind')->all());
        $this->assertSame(1, $report->items()->where('kind', 'poster')->count());
        $this->assertSame('https://www.facebook.com/esprit/posts/1', $report->items()->where('kind', 'proof')->firstOrFail()->url);
        $this->assertNull($report->items()->where('kind', 'proof')->firstOrFail()->path);
        $this->assertSame(7.5, $report->selfEvaluation()['total']);
        $this->assertTrue(AppNotification::query()->where('user_id', $this->president->id)->where('type', 'activity.report.submitted')->exists());

        // En attente : le club ne modifie plus, seul le président décide.
        $this->actingAs($this->writer)->put($url, $this->complete(['summary' => 'Modifié']))->assertSessionHas('error');
        $this->assertSame('Quarante bénévoles ont nettoyé la plage.', $report->fresh()->summary);
        $this->actingAs($this->writer)->post("$url/valider")->assertForbidden();
        $this->actingAs($this->writer)->get($url)->assertOk()->assertSee('Banque Zitouna')->assertDontSee('Valider et transmettre');
        $this->actingAs($this->president)->get($url)->assertOk()->assertSee('Valider et transmettre au comité');

        $this->actingAs($this->president)->post("$url/valider")->assertSessionHas('status');
        $report->refresh();
        $this->assertSame('validated', $report->status);
        $this->assertSame($this->president->id, $report->validated_by);
        $this->assertTrue(AppNotification::query()->where('user_id', $this->juror->id)->where('type', 'activity.report.validated')->exists());
        $this->assertTrue(AppNotification::query()->where('user_id', $this->writer->id)->where('type', 'activity.report.validated')->exists());

        // Validé : verrouillé.
        $this->actingAs($this->writer)->put($url, $this->complete(['summary' => 'Trop tard']))->assertSessionHas('error');
        $this->actingAs($this->president)->post("$url/valider")->assertStatus(422);
    }

    public function test_president_can_send_the_report_back_with_remarks(): void
    {
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";
        $this->actingAs($this->writer)->put($url, $this->complete());

        $this->actingAs($this->president)->post("$url/renvoyer", [])->assertSessionHasErrors('president_comment');
        $this->actingAs($this->president)->post("$url/renvoyer", ['president_comment' => 'Précisez le budget.'])->assertSessionHas('status');

        $report = ActivityReport::query()->firstOrFail();
        $this->assertSame('draft', $report->status);
        $this->assertSame('Précisez le budget.', $report->president_comment);
        $this->assertTrue(AppNotification::query()->where('user_id', $this->writer->id)->where('type', 'activity.report.returned')->exists());

        $this->actingAs($this->writer)->get($url)->assertOk()->assertSee('Précisez le budget.');
        $this->actingAs($this->writer)->put($url, $this->complete(['budget_notes' => 'Budget détaillé']))->assertSessionHas('status');
        $this->assertSame('pending', $report->fresh()->status);
        $this->assertNull($report->fresh()->president_comment);
    }

    public function test_only_members_of_the_organizing_clubs_are_rated(): void
    {
        $stranger = User::factory()->create();
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";

        $this->actingAs($this->writer)->put($url, $this->complete(['members' => [['user_id' => $stranger->id, 'implication' => 3]]]))->assertSessionHasErrors('members.0.user_id');
        $this->actingAs($this->writer)->put($url, $this->complete(['members' => [['user_id' => $this->member->id, 'role' => 'Accueil']]]))->assertSessionHasErrors('members.0.implication');
        $this->actingAs($this->writer)->put($url, $this->complete(['media' => [['kind' => 'instagram', 'label' => 'Story']]]))->assertSessionHasErrors('media.0.url');
        $this->assertSame(0, ActivityReport::query()->count());
    }

    public function test_proof_must_be_a_facebook_publication_and_nothing_is_uploaded(): void
    {
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";

        $this->actingAs($this->writer)->put($url, $this->complete(['proof_url' => 'https://example.com/photos']))->assertSessionHasErrors('proof_url');
        $this->actingAs($this->writer)->put($url, $this->complete(['proof_url' => 'http://www.facebook.com/esprit/posts/1']))->assertSessionHasErrors('proof_url');
        $this->actingAs($this->writer)->put($url, $this->complete(['proof_url' => 'https://facebook.com.evil.test/x']))->assertSessionHasErrors('proof_url');
        $this->actingAs($this->writer)->put($url, $this->complete(['proof_url' => 'javascript:alert(1)']))->assertSessionHasErrors('proof_url');
        $this->assertSame(0, ActivityReport::query()->count());

        $this->actingAs($this->writer)->put($url, $this->complete(['proof_url' => 'https://fb.watch/abc123/']))->assertSessionHasNoErrors();
        $this->actingAs($this->writer)->get($url)->assertOk()->assertDontSee('enctype="multipart/form-data"', false)->assertSee('fb.watch/abc123', false);
    }

    public function test_legacy_uploaded_proofs_are_removed_when_the_report_is_saved(): void
    {
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";
        $this->actingAs($this->writer)->put($url, $this->complete(['action' => 'draft']));
        $report = ActivityReport::query()->firstOrFail();
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('activity-reports/old.jpg', 'x');
        $report->items()->create(['kind' => 'proof', 'path' => 'activity-reports/old.jpg']);

        $this->actingAs($this->writer)->put($url, $this->complete(['action' => 'draft']))->assertSessionHasNoErrors();

        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('activity-reports/old.jpg');
        $this->assertSame(0, $report->items()->whereNotNull('path')->count());
        $this->assertSame(1, $report->items()->where('kind', 'proof')->count());
    }

    public function test_self_evaluation_is_required_bounded_and_kept_out_of_the_committee_score(): void
    {
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";

        $this->actingAs($this->writer)->put($url, $this->complete(['self' => [$this->criterion->id => ['points' => '11']]]))->assertSessionHasErrors("self.{$this->criterion->id}.points");
        $this->actingAs($this->writer)->put($url, $this->complete(['self' => []]))->assertSessionHasErrors('self');

        $this->actingAs($this->writer)->put($url, $this->complete(['action' => 'draft']))->assertSessionHasNoErrors();
        $report = ActivityReport::query()->firstOrFail();
        $this->assertSame(7.5, $report->selfEvaluation()['total']);
        $this->assertSame('Belle mobilisation', $report->self_strengths);
        $this->assertSame(0, \App\Models\ActivityScore::query()->count());

        $this->actingAs($this->writer)->get($url)->assertOk()->assertSee('Auto-évaluation')->assertSee('Bon impact local')->assertSee('Avis des participants');
    }

    public function test_participant_feedback_is_aggregated_anonymously_in_the_report(): void
    {
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";
        $first = User::factory()->create(['name' => 'Karim Anonyme']);
        $second = User::factory()->create();
        $this->activity->registrations()->create(['user_id' => $first->id, 'status' => 'registered', 'checked_in_at' => now(), 'feedback_overall' => 5, 'feedback_organization' => 4, 'feedback_content' => 5, 'feedback_comment' => 'Superbe journée', 'feedback_at' => now()]);
        $this->activity->registrations()->create(['user_id' => $second->id, 'status' => 'registered', 'checked_in_at' => now(), 'feedback_overall' => 3, 'feedback_organization' => 4, 'feedback_content' => 3, 'feedback_at' => now()]);

        $feedback = $this->complete(['action' => 'draft']);
        $this->actingAs($this->writer)->put($url, $feedback);
        $report = ActivityReport::query()->firstOrFail();
        $stats = $report->participantFeedback();

        $this->assertSame(2, $stats['count']);
        $this->assertSame(4.0, $stats['overall']);
        $this->assertSame(4.0, $stats['organization']);
        $this->assertSame(['Superbe journée'], $stats['comments']);

        $this->actingAs($this->writer)->get($url)->assertOk()->assertSee('Superbe journée')->assertDontSee('Karim Anonyme');
    }

    public function test_only_the_organizing_club_and_a_vacant_presidency_admin_can_act(): void
    {
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";
        $rival = app(OrgChartInstaller::class)->createClub(OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail(), 'ISET', 'university_club', Governorate::query()->first(), true);
        $rivalPresident = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $rival->title)->where('role_key', 'president')->firstOrFail(), $rivalPresident);

        $this->actingAs($rivalPresident)->get($url)->assertForbidden();
        $this->actingAs($rivalPresident)->put($url, $this->complete())->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $this->actingAs($this->writer)->put($url, $this->complete());
        $this->actingAs($rivalPresident)->post("$url/valider")->assertForbidden();

        // Sans président en poste, un administrateur à droits globaux valide à sa place.
        \App\Models\OrgAssignment::query()->where('node_id', OrgNode::query()->where('subtitle', $this->club->title)->where('role_key', 'president')->value('id'))->delete();
        $this->actingAs($this->writer)->post("$url/valider")->assertForbidden();
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $this->actingAs($admin)->post("$url/valider")->assertSessionHas('status');
        $this->assertSame('validated', ActivityReport::query()->firstOrFail()->status);
    }

    public function test_committee_scores_only_activities_with_a_validated_report(): void
    {
        $challenge = $this->activity->challenge;
        $criterion = $challenge->criteria()->first();
        $url = "/admin/activites/{$this->activity->id}/compte-rendu";
        $score = ['points' => [$criterion->id => 8]];

        \Illuminate\Support\Carbon::setTestNow($challenge->ends_at->copy()->addDay());

        $this->actingAs($this->juror)->get("/defis/{$challenge->id}/evaluation")->assertOk()->assertSee('Compte rendu pas encore remis')->assertDontSee('Enregistrer mes notes');
        $this->actingAs($this->juror)->post("/defis/{$challenge->id}/evaluation/{$this->activity->id}", $score)->assertSessionHas('error');

        $this->actingAs($this->writer)->put($url, $this->complete());
        $this->actingAs($this->juror)->get("/defis/{$challenge->id}/evaluation")->assertOk()->assertSee('en attente de validation')->assertDontSee('Quarante bénévoles');
        $this->actingAs($this->juror)->post("/defis/{$challenge->id}/evaluation/{$this->activity->id}", $score)->assertSessionHas('error');

        $this->actingAs($this->president)->post("$url/valider");
        $this->actingAs($this->juror)->get("/defis/{$challenge->id}/evaluation")->assertOk()->assertSee('Quarante bénévoles ont nettoyé la plage.')->assertSee('Banque Zitouna')->assertSee('Enregistrer mes notes');
        $this->actingAs($this->juror)->post("/defis/{$challenge->id}/evaluation/{$this->activity->id}", $score)->assertSessionHas('status');
    }
}
