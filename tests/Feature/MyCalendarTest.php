<?php

namespace Tests\Feature;

use App\Models\AcademyCourse;
use App\Models\AcademyEnrollment;
use App\Models\AcademySession;
use App\Models\ActivityRegistration;
use App\Models\ActivityTask;
use App\Models\ClubActivity;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\MyCalendar;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Calendrier de « Mon bureau » : tâches, formations, événements, activités et réunions du membre. */
class MyCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private OrgNode $club;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-17 10:00:00');

        $this->seed(GovernorateSeeder::class);
        app(OrgChartInstaller::class)->install();
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $this->club = app(OrgChartInstaller::class)->createClub($branch, 'ESPRIT', 'university_club', Governorate::query()->first(), true);
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function calendar(?User $user = null, string $month = '2026-11', array $clubIds = []): array
    {
        return MyCalendar::month($user ?? $this->user, MyCalendar::parseMonth($month), $clubIds);
    }

    /** @return array<int, array<string, mixed>> */
    private function entries(array $calendar): array
    {
        return collect($calendar['agenda'])->flatMap(fn (array $group): array => $group['entries'])->values()->all();
    }

    private function activity(array $overrides = []): ClubActivity
    {
        return ClubActivity::query()->create($overrides + [
            'challenge_id' => null, 'club_id' => $this->club->id, 'title' => 'Conférence libre', 'type' => 'conference', 'audience' => 'public',
            'in_theme' => false, 'starts_at' => '2026-11-20 14:00:00', 'status' => 'published',
        ]);
    }

    private function course(string $slug = 'charte'): AcademyCourse
    {
        return AcademyCourse::query()->create(['title' => 'Charte '.$slug, 'slug' => $slug, 'level' => 'beginner', 'status' => 'published']);
    }

    public function test_month_parameter_is_validated_and_falls_back_to_the_current_month(): void
    {
        $this->assertSame('2026-03', MyCalendar::parseMonth('2026-03')->format('Y-m'));

        foreach (['2026-13', 'abc', '', null, ['2026-03'], '2026-3', '1999-01'] as $invalid) {
            $this->assertSame('2026-11', MyCalendar::parseMonth($invalid)->format('Y-m'));
        }
    }

    public function test_the_grid_covers_whole_weeks_starting_on_monday_and_navigation_is_provided(): void
    {
        $calendar = $this->calendar();

        $this->assertSame('2026-10', $calendar['prev']);
        $this->assertSame('2026-12', $calendar['next']);
        $this->assertTrue($calendar['is_current']);
        $this->assertSame('2026-10-26', $calendar['weeks'][0][0]['date']->toDateString());
        $this->assertSame('2026-12-06', $calendar['weeks'][array_key_last($calendar['weeks'])][6]['date']->toDateString());

        foreach ($calendar['weeks'] as $week) {
            $this->assertCount(7, $week);
        }

        $today = collect($calendar['weeks'])->flatten(1)->firstWhere('today', true);
        $this->assertSame('2026-11-17', $today['date']->toDateString());
        $this->assertFalse($this->calendar(month: '2026-12')['is_current']);
    }

    public function test_tasks_appear_on_their_due_date_for_assignees_only(): void
    {
        $activity = $this->activity();
        $mine = ActivityTask::query()->create(['activity_id' => $activity->id, 'team' => 'event', 'label' => 'Réserver la salle', 'due_at' => '2026-11-12', 'status' => 'todo']);
        $mine->assignees()->attach($this->user->id);
        $done = ActivityTask::query()->create(['activity_id' => $activity->id, 'team' => 'event', 'label' => 'Imprimer les badges', 'due_at' => '2026-11-25', 'status' => 'done']);
        $done->assignees()->attach($this->user->id);
        $other = ActivityTask::query()->create(['activity_id' => $activity->id, 'team' => 'event', 'label' => 'Tâche des autres', 'due_at' => '2026-11-13', 'status' => 'todo']);
        $other->assignees()->attach(User::factory()->create()->id);
        ActivityTask::query()->create(['activity_id' => $activity->id, 'team' => 'event', 'label' => 'Sans responsable', 'due_at' => '2026-11-14', 'status' => 'todo']);

        $calendar = $this->calendar();
        $entries = collect($this->entries($calendar))->keyBy('title');

        $this->assertSame(['Imprimer les badges', 'Réserver la salle'], $entries->keys()->sort()->values()->all());
        $this->assertSame('task', $entries['Réserver la salle']['kind']);
        $this->assertTrue($entries['Réserver la salle']['all_day']);
        $this->assertSame('late', $entries['Réserver la salle']['state']);
        $this->assertSame('done', $entries['Imprimer les badges']['state']);
        $this->assertSame(2, $calendar['counts']['task']);
        $this->assertArrayHasKey('2026-11-12', $calendar['agenda']);
        $this->assertSame(route('tasks.mine'), $entries['Réserver la salle']['url']);
    }

    public function test_academy_sessions_only_show_for_enrolled_courses(): void
    {
        $mine = $this->course('charte');
        $other = $this->course('autre');
        AcademyEnrollment::query()->create(['user_id' => $this->user->id, 'course_id' => $mine->id, 'source' => 'free', 'enrolled_at' => now()]);
        AcademySession::query()->create(['course_id' => $mine->id, 'title' => 'Classe virtuelle', 'starts_at' => '2026-11-18 18:00:00', 'ends_at' => '2026-11-18 19:30:00', 'is_published' => true]);
        AcademySession::query()->create(['course_id' => $mine->id, 'title' => 'Session brouillon', 'starts_at' => '2026-11-19 18:00:00', 'is_published' => false]);
        AcademySession::query()->create(['course_id' => $other->id, 'title' => 'Session d’un autre cours', 'starts_at' => '2026-11-19 18:00:00', 'is_published' => true]);

        $entries = $this->entries($this->calendar());

        $this->assertCount(1, $entries);
        $this->assertSame('training', $entries[0]['kind']);
        $this->assertSame('Classe virtuelle', $entries[0]['title']);
        $this->assertSame('18:00', $entries[0]['time']);
    }

    public function test_events_come_from_my_registrations_and_from_my_clubs(): void
    {
        $registered = FoundationEvent::query()->create(['title' => 'Forum Robotique', 'status' => 'published', 'starts_at' => '2026-11-21 09:00:00', 'location' => 'Tunis']);
        EventRegistration::query()->create(['event_id' => $registered->id, 'user_id' => $this->user->id, 'status' => 'confirmed']);
        $cancelled = FoundationEvent::query()->create(['title' => 'Atelier annulé par moi', 'status' => 'published', 'starts_at' => '2026-11-22 09:00:00']);
        EventRegistration::query()->create(['event_id' => $cancelled->id, 'user_id' => $this->user->id, 'status' => 'cancelled']);
        FoundationEvent::query()->create(['title' => 'Hackathon du club', 'node_id' => $this->club->id, 'status' => 'published', 'starts_at' => '2026-11-23 09:00:00']);
        FoundationEvent::query()->create(['title' => 'Brouillon du club', 'node_id' => $this->club->id, 'status' => 'draft', 'starts_at' => '2026-11-24 09:00:00']);
        FoundationEvent::query()->create(['title' => 'Événement étranger', 'status' => 'published', 'starts_at' => '2026-11-25 09:00:00']);

        $withoutClub = collect($this->entries($this->calendar()))->pluck('title')->all();
        $withClub = collect($this->entries($this->calendar(clubIds: [$this->club->id])))->pluck('title')->all();

        $this->assertSame(['Forum Robotique'], $withoutClub);
        $this->assertSame(['Forum Robotique', 'Hackathon du club'], $withClub);
    }

    public function test_multi_day_events_span_every_day_with_the_time_on_the_first_one(): void
    {
        $event = FoundationEvent::query()->create(['title' => 'Camp d’hiver', 'status' => 'published', 'starts_at' => '2026-11-26 09:00:00', 'ends_at' => '2026-11-28 17:00:00']);
        EventRegistration::query()->create(['event_id' => $event->id, 'user_id' => $this->user->id, 'status' => 'confirmed']);

        $calendar = $this->calendar();

        $this->assertSame(['2026-11-26', '2026-11-27', '2026-11-28'], array_keys($calendar['agenda']));
        $this->assertSame('09:00', $calendar['agenda']['2026-11-26']['entries'][0]['time']);
        $this->assertNull($calendar['agenda']['2026-11-27']['entries'][0]['time']);
        $this->assertSame(1, $calendar['counts']['event']);
    }

    public function test_an_event_started_in_the_previous_month_continues_into_the_displayed_month(): void
    {
        $event = FoundationEvent::query()->create(['title' => 'Tournoi', 'status' => 'published', 'starts_at' => '2026-10-30 09:00:00', 'ends_at' => '2026-11-02 12:00:00']);
        EventRegistration::query()->create(['event_id' => $event->id, 'user_id' => $this->user->id, 'status' => 'confirmed']);

        $calendar = $this->calendar();

        $this->assertSame(['2026-11-01', '2026-11-02'], array_keys($calendar['agenda']));
        $this->assertSame(0, $calendar['counts']['event']);
    }

    public function test_activities_i_registered_to_show_and_trainings_count_as_formations(): void
    {
        $talk = $this->activity(['title' => 'Conférence IA']);
        $workshop = $this->activity(['title' => 'Atelier Laravel', 'type' => 'training', 'starts_at' => '2026-11-27 10:00:00']);
        $notMine = $this->activity(['title' => 'Activité sans moi']);
        $draft = $this->activity(['title' => 'Activité brouillon', 'status' => 'draft']);
        $absent = $this->activity(['title' => 'Inscription annulée']);

        foreach ([$talk, $workshop, $draft] as $activity) {
            ActivityRegistration::query()->create(['activity_id' => $activity->id, 'user_id' => $this->user->id, 'status' => 'registered']);
        }
        ActivityRegistration::query()->create(['activity_id' => $absent->id, 'user_id' => $this->user->id, 'status' => 'cancelled']);

        $entries = collect($this->entries($this->calendar()))->keyBy('title');

        $this->assertSame(['Atelier Laravel', 'Conférence IA'], $entries->keys()->sort()->values()->all());
        $this->assertSame('activity', $entries['Conférence IA']['kind']);
        $this->assertSame('training', $entries['Atelier Laravel']['kind']);
        $this->assertNotContains($notMine->title, $entries->keys()->all());
    }

    public function test_meetings_show_when_invited_except_declined_or_cancelled(): void
    {
        $make = function (string $title, string $status, string $response, string $at) {
            $meeting = Meeting::query()->create(['club_id' => $this->club->id, 'title' => $title, 'starts_at' => $at, 'duration_minutes' => 60, 'organizer_id' => $this->user->id, 'status' => $status]);
            MeetingAttendee::query()->create(['meeting_id' => $meeting->id, 'user_id' => $this->user->id, 'response' => $response, 'invited_at' => now()]);
        };

        $make('Bureau du club', 'planned', 'pending', '2026-11-19 17:00:00');
        $make('Réunion déclinée', 'planned', 'declined', '2026-11-20 17:00:00');
        $make('Réunion annulée', 'cancelled', 'pending', '2026-11-21 17:00:00');
        Meeting::query()->create(['club_id' => $this->club->id, 'title' => 'Réunion sans moi', 'starts_at' => '2026-11-22 17:00:00', 'duration_minutes' => 60, 'organizer_id' => $this->user->id, 'status' => 'planned']);

        $entries = $this->entries($this->calendar());

        $this->assertCount(1, $entries);
        $this->assertSame('meeting', $entries[0]['kind']);
        $this->assertSame('Bureau du club', $entries[0]['title']);
        $this->assertSame('17:00', $entries[0]['time']);
    }

    public function test_other_members_items_never_leak_and_other_months_stay_empty(): void
    {
        $event = FoundationEvent::query()->create(['title' => 'Forum Robotique', 'status' => 'published', 'starts_at' => '2026-11-21 09:00:00']);
        EventRegistration::query()->create(['event_id' => $event->id, 'user_id' => User::factory()->create()->id, 'status' => 'confirmed']);

        $this->assertSame(0, $this->calendar()['total']);
        $this->assertSame([], $this->calendar(month: '2027-02')['agenda']);
    }

    public function test_upcoming_lists_the_next_items_in_order_and_skips_done_tasks(): void
    {
        $activity = $this->activity();
        $done = ActivityTask::query()->create(['activity_id' => $activity->id, 'team' => 'event', 'label' => 'Déjà faite', 'due_at' => '2026-11-18', 'status' => 'done']);
        $done->assignees()->attach($this->user->id);

        foreach ([['Plus tard', '2026-11-30 09:00:00'], ['Bientôt', '2026-11-18 09:00:00']] as [$title, $at]) {
            $event = FoundationEvent::query()->create(['title' => $title, 'status' => 'published', 'starts_at' => $at]);
            EventRegistration::query()->create(['event_id' => $event->id, 'user_id' => $this->user->id, 'status' => 'confirmed']);
        }

        $this->assertSame(['Bientôt', 'Plus tard'], collect($this->calendar()['upcoming'])->pluck('title')->all());
    }

    public function test_the_page_shows_the_calendar_and_navigates_by_month(): void
    {
        $event = FoundationEvent::query()->create(['title' => 'Forum <b>Robotique</b>', 'status' => 'published', 'starts_at' => '2026-11-21 09:00:00']);
        EventRegistration::query()->create(['event_id' => $event->id, 'user_id' => $this->user->id, 'status' => 'confirmed']);

        $this->actingAs($this->user)->get('/mon-bureau')->assertOk()
            ->assertSee('Mon calendrier')
            ->assertSee('Novembre 2026')
            ->assertSee('Tâches')->assertSee('Formations')->assertSee('Événements')->assertSee('Activités')->assertSee('Réunions')
            ->assertSee('Forum &lt;b&gt;Robotique&lt;/b&gt;', false)
            ->assertDontSee('Forum <b>Robotique</b>', false)
            ->assertSee('mois=2026-10', false)
            ->assertSee('mois=2026-12', false);

        $this->actingAs($this->user)->get('/mon-bureau?mois=2027-01')->assertOk()
            ->assertSee('Janvier 2027')
            ->assertSee('Aujourd’hui')
            ->assertSee('Rien ce mois-ci');

        $this->actingAs($this->user)->get('/mon-bureau?mois=n-importe-quoi')->assertOk()->assertSee('Novembre 2026');
    }

    public function test_guests_cannot_reach_the_page(): void
    {
        $this->get('/mon-bureau')->assertRedirect();
    }
}
