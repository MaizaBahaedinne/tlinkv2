<?php

namespace Tests\Feature;

use App\Models\ActivityReport;
use App\Models\ActivityTask;
use App\Models\AppNotification;
use App\Models\ClubActivity;
use App\Models\Governorate;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\Challenges\ActivityTaskTemplates;
use Database\Seeders\GovernorateSeeder;
use App\Support\OrgChart\OrgChartInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Activités libres (sans défi) et tâches types : reprise, ajout, affectation, suivi et envoi avec le compte rendu. */
class ActivityTasksTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private OrgNode $club;

    private User $president;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernorateSeeder::class);
        app(OrgChartInstaller::class)->install();
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $this->club = app(OrgChartInstaller::class)->createClub($branch, 'ESPRIT', 'university_club', Governorate::query()->first(), true);

        $this->president = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $this->club->title)->where('role_key', 'president')->firstOrFail(), $this->president);

        $this->member = User::factory()->create(['name' => 'Salma Ben Ali']);
        Membership::query()->create(['user_id' => $this->member->id, 'scope' => 'university', 'node_id' => $this->club->id, 'status' => 'approved', 'membership_type' => 'member']);
    }

    private function freeActivity(array $overrides = []): ClubActivity
    {
        return ClubActivity::query()->create($overrides + [
            'challenge_id' => null, 'club_id' => $this->club->id, 'title' => 'Conférence libre', 'type' => 'conference', 'audience' => 'public',
            'in_theme' => false, 'starts_at' => now()->addDays(40), 'status' => 'published',
        ]);
    }

    private function tasksUrl(ClubActivity $activity, string $suffix = ''): string
    {
        return "/admin/activites/{$activity->id}/taches{$suffix}";
    }

    public function test_every_activity_type_has_a_predefined_list_with_the_four_teams(): void
    {
        foreach (array_keys(\App\Models\ClubActivity::TYPES) as $type) {
            $set = ActivityTaskTemplates::forType($type);

            $this->assertSame(array_keys(ActivityTaskTemplates::TEAMS), array_keys($set['teams']), $type);

            foreach ($set['teams'] as $labels) {
                $this->assertCount(6, $labels, $type);
            }

            $this->assertCount(24, ActivityTaskTemplates::tasksForType($type), $type);
        }

        $this->assertSame('Définir le thème, les objectifs et le format de la conférence.', ActivityTaskTemplates::forType('conference')['teams']['event'][0]);
        $this->assertSame('Organiser la remise des prix et publier le classement final.', ActivityTaskTemplates::forType('competition')['teams']['event'][5]);
    }

    public function test_a_club_creates_an_activity_without_any_challenge(): void
    {
        $this->actingAs($this->president)->get('/admin/activites/creer')->assertOk()->assertSee('Aucun défi (activité libre)');

        $this->actingAs($this->president)->post('/admin/activites', [
            'club_id' => $this->club->id, 'title' => 'Soirée de rentrée', 'type' => 'party', 'audience' => 'public',
            'in_theme' => 1, 'status' => 'published', 'starts_at' => now()->addDays(60)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $activity = ClubActivity::query()->where('title', 'Soirée de rentrée')->firstOrFail();
        $this->assertNull($activity->challenge_id);
        $this->assertFalse($activity->in_theme);
        $this->assertTrue($activity->isPublished());

        $this->actingAs($this->president)->get("/admin/activites/{$activity->id}")->assertOk()->assertSee('Activité libre')->assertSee('Tâches de l’activité');
        $this->actingAs($this->member)->get("/activites/{$activity->id}")->assertOk()->assertSee('Soirée de rentrée');
        $this->assertSame(['Soirée de rentrée'], collect(\App\Support\Challenges\ActivityCalendar::for($this->member))->pluck('title')->all());

        // Un club ne peut pas porter l'activité d'un autre club.
        $other = User::factory()->create();
        $this->actingAs($other)->post('/admin/activites', ['club_id' => $this->club->id, 'title' => 'X', 'type' => 'event', 'audience' => 'public', 'status' => 'draft', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i')])->assertForbidden();
    }

    public function test_the_creator_takes_the_predefined_list_of_the_activity_type(): void
    {
        $activity = $this->freeActivity(['type' => 'camping']);

        $this->actingAs($this->president)->post($this->tasksUrl($activity, '/modele'))->assertRedirect()->assertSessionHas('status');

        $this->assertSame(24, $activity->tasks()->count());
        $this->assertSame(6, $activity->tasks()->where('team', 'sponsoring')->count());
        $this->assertTrue($activity->tasks()->where('team', 'hr')->where('label', 'Définir les responsables du séjour et les encadrants.')->exists());
        $this->assertSame(24, $activity->tasks()->where('from_template', true)->where('status', 'todo')->count());

        // Reprendre une seconde fois ne duplique rien ; une tâche supprimée revient.
        $this->actingAs($this->president)->post($this->tasksUrl($activity, '/modele'));
        $this->assertSame(24, $activity->tasks()->count());

        $activity->tasks()->first()->delete();
        $this->actingAs($this->president)->post($this->tasksUrl($activity, '/modele'));
        $this->assertSame(24, $activity->tasks()->count());

        $this->actingAs($this->president)->get("/admin/activites/{$activity->id}")->assertOk()->assertSee('Liste type « Camping / Retreat »')->assertSee('Organiser le retour et établir le bilan du séjour.');
    }

    public function test_tasks_are_adapted_added_assigned_and_followed(): void
    {
        $activity = $this->freeActivity();

        $this->actingAs($this->president)->post($this->tasksUrl($activity), ['label' => 'Réserver le traiteur', 'team' => 'event', 'assignee_id' => $this->member->id, 'due_at' => now()->addDays(10)->format('Y-m-d')])
            ->assertSessionHasNoErrors();

        $task = $activity->tasks()->firstOrFail();
        $this->assertSame($this->member->id, $task->assignee_id);
        $this->assertFalse($task->from_template);
        $this->assertSame(1, AppNotification::query()->where('user_id', $this->member->id)->where('type', 'activity.task.assigned')->count());

        // Suivi : en cours, puis terminée ; l'intitulé s'adapte et la tâche change d'équipe.
        $this->actingAs($this->president)->patch($this->tasksUrl($activity, "/{$task->id}"), ['label' => 'Réserver le traiteur halal', 'team' => 'sponsoring', 'assignee_id' => $this->member->id, 'status' => 'doing', 'notes' => 'Devis reçu'])
            ->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertSame(['Réserver le traiteur halal', 'sponsoring', 'doing', 'Devis reçu'], [$task->label, $task->team, $task->status, $task->notes]);
        $this->assertNull($task->completed_at);
        $this->assertSame(1, AppNotification::query()->where('type', 'activity.task.assigned')->count(), 'Même responsable : pas de nouvelle notification.');

        $this->actingAs($this->president)->patch($this->tasksUrl($activity, "/{$task->id}"), ['label' => $task->label, 'team' => 'sponsoring', 'status' => 'done']);
        $task->refresh();
        $this->assertTrue($task->isDone());
        $this->assertNotNull($task->completed_at);
        $this->assertNull($task->assignee_id);

        $this->actingAs($this->president)->delete($this->tasksUrl($activity, "/{$task->id}"))->assertSessionHas('status');
        $this->assertSame(0, $activity->tasks()->count());
    }

    public function test_only_members_of_the_organizing_clubs_can_be_assigned_and_only_their_managers_edit(): void
    {
        $activity = $this->freeActivity();
        $stranger = User::factory()->create();

        $this->actingAs($this->president)->post($this->tasksUrl($activity), ['label' => 'Tâche', 'team' => 'hr', 'assignee_id' => $stranger->id])->assertSessionHasErrors('assignee_id');
        $this->actingAs($this->president)->post($this->tasksUrl($activity), ['label' => '', 'team' => 'nimporte'])->assertSessionHasErrors(['label', 'team']);
        $this->assertSame(0, $activity->tasks()->count());

        $task = $activity->tasks()->create(['team' => 'hr', 'label' => 'Briefing']);

        $this->actingAs($stranger)->post($this->tasksUrl($activity), ['label' => 'Intrus', 'team' => 'hr'])->assertForbidden();
        $this->actingAs($stranger)->patch($this->tasksUrl($activity, "/{$task->id}"), ['label' => 'Intrus', 'team' => 'hr'])->assertForbidden();
        $this->actingAs($stranger)->delete($this->tasksUrl($activity, "/{$task->id}"))->assertForbidden();
        $this->assertSame('Briefing', $task->fresh()->label);

        // Une tâche d'une autre activité n'est pas modifiable via celle-ci.
        $other = $this->freeActivity(['title' => 'Autre']);
        $this->actingAs($this->president)->patch($this->tasksUrl($other, "/{$task->id}"), ['label' => 'Détournée', 'team' => 'hr'])->assertNotFound();
    }

    public function test_the_task_list_goes_with_the_report_and_is_frozen_once_submitted(): void
    {
        $activity = $this->freeActivity(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHours(3)]);
        $activity->tasks()->create(['team' => 'marketing', 'label' => 'Publier le récapitulatif', 'status' => 'done', 'assignee_id' => $this->member->id]);
        $activity->tasks()->create(['team' => 'event', 'label' => 'Remercier les intervenants', 'status' => 'todo']);

        $url = "/admin/activites/{$activity->id}/compte-rendu";

        $this->actingAs($this->president)->get($url)->assertOk()->assertSee('Publier le récapitulatif')->assertSee('Remercier les intervenants')->assertSee('1 / 2')->assertDontSee('Auto-évaluation');

        // Sans défi : pas d'auto-évaluation ni de barème ; le président valide seul.
        $this->actingAs($this->president)->put($url, [
            'action' => 'submit', 'summary' => 'Une belle conférence.',
            'members' => [['user_id' => $this->member->id, 'role' => 'Logistique', 'implication' => 4]],
            'proof_url' => 'https://www.facebook.com/esprit/posts/1', 'budget_spent' => '100',
        ])->assertSessionHasNoErrors();

        $report = ActivityReport::query()->firstOrFail();
        $this->assertSame('pending', $report->status);

        // Figées : plus d'ajout, de modification ni de suppression.
        $task = $activity->tasks()->where('team', 'event')->firstOrFail();
        $this->actingAs($this->president)->post($this->tasksUrl($activity), ['label' => 'Trop tard', 'team' => 'hr'])->assertSessionHasErrors('task');
        $this->actingAs($this->president)->patch($this->tasksUrl($activity, "/{$task->id}"), ['label' => 'Modifiée', 'team' => 'event', 'status' => 'done'])->assertSessionHasErrors('task');
        $this->actingAs($this->president)->delete($this->tasksUrl($activity, "/{$task->id}"))->assertSessionHasErrors('task');
        $this->assertSame(2, $activity->tasks()->count());
        $this->assertSame('todo', $task->fresh()->status);

        $this->actingAs($this->president)->post("/admin/activites/{$activity->id}/compte-rendu/valider")->assertSessionHas('status', 'Compte rendu validé.');
        $this->assertTrue($report->fresh()->isValidated());

        $this->actingAs($this->president)->get("/admin/activites/{$activity->id}")->assertOk()->assertSee('Les tâches sont figées');
    }
}
