<?php

namespace App\Http\Controllers\Admin\Events;

use App\Http\Controllers\Admin\EventController;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Support\Events\EventAccess;
use App\Support\Events\EventRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventOverviewController extends EventManageController
{
    /** Événements dont l'utilisateur fait partie de l'équipe OC ou dont il est responsable de bus. */
    public function mine(Request $request): View
    {
        $userId = $request->user()->id;

        $events = FoundationEvent::query()
            ->where(fn ($query) => $query
                ->whereHas('team', fn ($team) => $team->where('user_id', $userId))
                ->orWhereHas('buses.managers', fn ($manager) => $manager->where('user_id', $userId)))
            ->with(['team' => fn ($query) => $query->where('user_id', $userId)])
            ->orderByDesc('starts_at')
            ->get();

        return view('admin.events.manage.mine', ['events' => $events]);
    }

    public function overview(Request $request, FoundationEvent $event): View
    {
        $this->allow($request, $event, 'view');

        $registrations = $event->registrations()->get();

        return $this->render($request, $event, 'overview', 'admin.events.manage.overview', [
            'counts' => $registrations->countBy('status'),
            'paid' => (float) $event->payments()->where('status', 'validated')->sum('amount'),
            'pendingPayments' => $event->payments()->where('status', 'pending')->count(),
            'roleLabel' => EventAccess::ROLES[EventAccess::role($request->user(), $event)] ?? null,
            'team' => $event->team()->with('user:id,name')->get(),
            'sessionsCount' => $event->sessions()->count(),
            'releasesCount' => $event->releases()->count(),
        ]);
    }

    public function registrations(Request $request, FoundationEvent $event): View
    {
        $this->allow($request, $event, 'registrations');

        return $this->render($request, $event, 'registrations', 'admin.events.manage.registrations', [
            'registrations' => $event->registrations()->with(['user:id,name,email', 'release:id,name'])->latest('id')->get(),
            'statuses' => EventController::REGISTRATION_STATUSES,
        ]);
    }

    public function updateRegistration(Request $request, FoundationEvent $event, EventRegistration $registration): RedirectResponse
    {
        $this->allow($request, $event, 'registrations');
        abort_unless($registration->event_id === $event->id, 404);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(EventController::REGISTRATION_STATUSES))]]);

        $from = $registration->status;
        $registration->update(['status' => $data['status']]);

        if (in_array($data['status'], ['cancelled', 'rejected'], true)) {
            app(EventRegistrar::class)->promoteWaitlist($event);
        }

        $this->audit($request, 'admin.event.registration_updated', $registration, [
            'event_id' => $event->id, 'user_id' => $registration->user_id, 'from' => $from, 'to' => $data['status'],
        ]);

        return back()->with('status', 'Inscription mise à jour.');
    }
}
