<?php

namespace App\Http\Controllers;

use App\Models\AcademyCourse;
use App\Models\AcademyEnrollment;
use App\Models\AcademyLesson;
use App\Models\AcademyOrder;
use App\Models\AcademyPlan;
use App\Models\AcademyQuizAttempt;
use App\Models\AcademySession;
use App\Models\AcademySubscription;
use App\Models\FoundationEvent;
use App\Support\Academy\AcademyAccess;
use App\Support\Academy\AcademyLearning;
use App\Support\Academy\AcademyOrders;
use App\Support\Academy\AcademySettings;
use App\Support\Academy\Payments\PaymentGateways;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace membre de Tunivisions Academy : catalogue, cours, leçons, commandes, calendrier et certificats.
 */
class AcademyController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $courses = AcademyCourse::query()->where('status', 'published')
            ->withCount(['modules', 'lessons'])
            ->orderBy('sort_order')->orderBy('id')->get();

        $enrolledIds = AcademyEnrollment::query()->where('user_id', $user->id)->pluck('course_id')->all();

        $progress = [];
        foreach ($courses->whereIn('id', $enrolledIds) as $course) {
            $progress[$course->id] = AcademyLearning::progress($user, $course);
        }

        return view('academy.index', [
            'courses' => $courses,
            'enrolledIds' => $enrolledIds,
            'progress' => $progress,
            'plans' => AcademyPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'subscription' => AcademySubscription::query()->with('plan')->where('user_id', $user->id)->where('ends_at', '>', now())->orderByDesc('ends_at')->first(),
            'points' => AcademyLearning::points($user->id),
            'salesEnabled' => (bool) AcademySettings::get('sales_enabled'),
            'name' => AcademySettings::NAME,
        ]);
    }

    public function show(Request $request, AcademyCourse $course): View
    {
        $course = $this->published($course);
        $user = $request->user();
        $course->load(['modules.lessons', 'badge']);

        $enrollment = AcademyAccess::enrollment($user, $course);
        $quizzes = $course->quizzes()->whereHas('questions')->withCount('questions')->get();

        return view('academy.course', [
            'course' => $course,
            'enrollment' => $enrollment,
            'quizByModule' => $quizzes->whereNotNull('module_id')->keyBy('module_id'),
            'finalQuiz' => $quizzes->whereNull('module_id')->first(),
            'passedQuizIds' => AcademyQuizAttempt::query()->where('user_id', $user->id)->where('status', 'passed')->whereIn('quiz_id', $quizzes->pluck('id'))->pluck('quiz_id')->all(),
            'canAccess' => AcademyAccess::canAccess($user, $course),
            'progress' => AcademyLearning::progress($user, $course),
            'sessions' => $course->sessions()->where('is_published', true)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
            'salesEnabled' => (bool) AcademySettings::get('sales_enabled'),
            'plans' => $course->included_in_plans ? AcademyPlan::query()->where('is_active', true)->orderBy('sort_order')->get() : collect(),
            'name' => AcademySettings::NAME,
        ]);
    }

    /** Démarre ou reprend un cours : mène à la première leçon non terminée. */
    public function start(Request $request, AcademyCourse $course): RedirectResponse
    {
        $course = $this->published($course);
        $user = $request->user();

        if (! AcademyAccess::canAccess($user, $course)) {
            return redirect()->route('academy.courses.show', $course->slug)->with('status', 'Ce cours n’est pas encore débloqué pour vous.');
        }

        AcademyAccess::enroll($user, $course, AcademyAccess::sourceFor($user, $course));

        $course->load('modules.lessons');
        $done = AcademyLearning::progress($user, $course)['completed_ids'];
        $lessons = $course->modules->flatMap->lessons;
        $next = $lessons->first(fn (AcademyLesson $lesson): bool => ! in_array($lesson->id, $done, true)) ?? $lessons->first();

        return $next === null
            ? redirect()->route('academy.courses.show', $course->slug)->with('status', 'Les leçons de ce cours arrivent bientôt.')
            : redirect()->route('academy.lessons.show', $next);
    }

    public function lesson(Request $request, AcademyLesson $lesson): View|RedirectResponse
    {
        $lesson->load('module.course');
        $course = $lesson->module->course;
        $user = $request->user();

        abort_unless($course->isPublished(), 404);

        if (! AcademyAccess::canView($user, $lesson)) {
            return redirect()->route('academy.courses.show', $course->slug)->with('status', 'Débloquez ce cours pour suivre cette leçon.');
        }

        $course->load('modules.lessons');
        $lessons = $course->modules->flatMap->lessons->values();
        $index = $lessons->search(fn (AcademyLesson $item): bool => $item->id === $lesson->id);
        $progress = AcademyLearning::progress($user, $course);

        return view('academy.lesson', [
            'lesson' => $lesson,
            'course' => $course,
            'previous' => $index > 0 ? $lessons[$index - 1] : null,
            'next' => $lessons[$index + 1] ?? null,
            'progress' => $progress,
            'completed' => in_array($lesson->id, $progress['completed_ids'], true),
            'canComplete' => AcademyAccess::canAccess($user, $course),
        ]);
    }

    public function complete(Request $request, AcademyLesson $lesson): RedirectResponse
    {
        $lesson->load('module.course');
        $course = $lesson->module->course;

        abort_unless($course->isPublished(), 404);

        if (! AcademyLearning::completeLesson($request->user(), $lesson)) {
            return redirect()->route('academy.courses.show', $course->slug)->with('status', 'Débloquez ce cours pour valider vos leçons.');
        }

        $enrollment = AcademyAccess::enrollment($request->user(), $course);

        if ($enrollment?->completed_at !== null) {
            return redirect()->route('academy.courses.show', $course->slug)->with('status', 'Bravo, cours terminé ! Votre certificat est disponible.');
        }

        $lessons = $course->load('modules.lessons')->modules->flatMap->lessons->values();
        $index = $lessons->search(fn (AcademyLesson $item): bool => $item->id === $lesson->id);
        $next = $lessons[$index + 1] ?? null;

        return $next === null
            ? redirect()->route('academy.courses.show', $course->slug)->with('status', 'Leçon validée.')
            : redirect()->route('academy.lessons.show', $next)->with('status', 'Leçon validée, +'.$lesson->points.' point(s).');
    }

    public function buyCourse(Request $request, AcademyCourse $course): RedirectResponse
    {
        $course = $this->published($course);
        $user = $request->user();

        if (AcademyAccess::canAccess($user, $course)) {
            return redirect()->route('academy.courses.show', $course->slug)->with('status', 'Vous avez déjà accès à ce cours.');
        }

        if (! AcademySettings::get('sales_enabled')) {
            return redirect()->route('academy.courses.show', $course->slug)->with('status', 'Les inscriptions payantes sont momentanément fermées.');
        }

        return redirect(PaymentGateways::active()->start(AcademyOrders::open($user, $course)));
    }

    public function subscribe(Request $request, AcademyPlan $plan): RedirectResponse
    {
        abort_unless($plan->is_active, 404);

        if (! AcademySettings::get('sales_enabled')) {
            return redirect()->route('academy.index')->with('status', 'Les abonnements sont momentanément fermés.');
        }

        return redirect(PaymentGateways::active()->start(AcademyOrders::open($request->user(), $plan)));
    }

    public function order(Request $request, AcademyOrder $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load('course');

        return view('academy.order', [
            'order' => $order,
            'instructions' => (string) AcademySettings::get('manual_instructions'),
            'contactEmail' => (string) AcademySettings::get('contact_email'),
            'name' => AcademySettings::NAME,
        ]);
    }

    public function calendar(Request $request): View
    {
        $user = $request->user();

        $sessions = AcademySession::query()->with('course')->where('is_published', true)
            ->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(60)->get();

        $events = FoundationEvent::query()->where('status', 'published')
            ->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(60)->get();

        $entries = $sessions->map(fn (AcademySession $session): array => [
            'kind' => 'session',
            'at' => $session->starts_at,
            'ends_at' => $session->ends_at,
            'title' => $session->title,
            'description' => $session->description,
            'location' => $session->location,
            'course' => $session->course,
            'meeting_url' => $session->course === null || AcademyAccess::canAccess($user, $session->course) ? $session->safeMeetingUrl() : null,
            'url' => null,
        ])->concat($events->map(function (FoundationEvent $event): array {
            $locale = array_key_exists(app()->getLocale(), config('site.locales')) ? app()->getLocale() : config('site.default_locale');

            return [
                'kind' => 'event',
                'at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'title' => $event->title,
                'description' => null,
                'location' => $event->location,
                'course' => null,
                'meeting_url' => null,
                'url' => route('site.events.show', ['locale' => $locale, 'event' => $event->id]),
            ];
        }))->sortBy(fn (array $entry) => $entry['at']->getTimestamp())->values();

        return view('academy.calendar', ['entries' => $entries, 'name' => AcademySettings::NAME]);
    }

    /** Certificat de fin de cours : page publique, vérifiable grâce à son code. */
    public function certificate(string $code): View
    {
        $enrollment = AcademyEnrollment::query()->with(['user', 'course'])
            ->where('certificate_code', $code)->whereNotNull('completed_at')->firstOrFail();

        return view('academy.certificate', ['enrollment' => $enrollment, 'name' => AcademySettings::NAME]);
    }

    private function published(AcademyCourse $course): AcademyCourse
    {
        abort_unless($course->isPublished(), 404);

        return $course;
    }
}
