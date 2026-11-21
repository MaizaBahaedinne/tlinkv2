<?php

namespace App\Http\Controllers;

use App\Models\EventPayment;
use App\Models\EventSession;
use App\Models\EventBusStop;
use App\Models\EventTransportBooking;
use App\Models\FoundationEvent;
use App\Support\Events\EventAccess;
use App\Support\Events\EventPayments;
use App\Support\Events\EventRegistrar;
use App\Support\Events\EventRegistrationException;
use App\Support\Events\EventTransport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Page membre d'un événement : inscription par release, paiement, étapes du programme. */
class MemberEventController extends Controller
{
    public function show(Request $request, FoundationEvent $event): View
    {
        $user = $request->user();
        $canManage = EventAccess::canView($user, $event);

        abort_unless($event->status === 'published' || $event->status === 'completed' || $canManage, 404);

        $registration = $event->registrations()->where('user_id', $user->id)->with(['release', 'payments'])->first();
        $active = $registration !== null && $registration->status !== 'cancelled';

        $registeredSessions = $active
            ? $registration->sessionRegistrations()->where('status', 'registered')->pluck('session_id')->all()
            : [];

        return view('events.show', [
            'event' => $event->load('governorate'),
            'registration' => $active ? $registration : null,
            'releases' => $event->releases()->where('is_active', true)->get(),
            'sessions' => $event->sessions()->with('speakers')->get(),
            'speakers' => $event->speakers()->get(),
            'levels' => $event->sponsorLevels()->with('sponsors')->get(),
            'registeredSessions' => $registeredSessions,
            'enabledMethods' => EventPayments::enabledMethods($event),
            'methods' => EventPayment::METHODS,
            'outstanding' => $active ? EventPayments::outstanding($registration) : 0.0,
            'canManage' => $canManage,
            'registrationOpen' => $this->registrationOpen($event),
            'transport' => $active && in_array($registration->status, ['pending', 'confirmed', 'attended'], true)
                ? $this->transportData($event, $registration)
                : null,
        ]);
    }

    /**
     * Régions et départs proposés à l'inscrit, et son choix actuel (null si l'événement n'a pas de transport).
     *
     * @return array<string, mixed>|null
     */
    private function transportData(FoundationEvent $event, $registration): ?array
    {
        $regions = $event->transportRegions()->get();

        if ($regions->isEmpty()) {
            return null;
        }

        $stops = EventBusStop::query()
            ->whereHas('bus', fn ($query) => $query->where('event_id', $event->id))
            ->with(['bus.managers.user:id,name', 'point'])
            ->orderBy('departs_at')
            ->get()
            ->each(fn (EventBusStop $stop) => $stop->bus->setAttribute('remaining_seats', $stop->bus->remaining()));

        return [
            'regions' => $regions,
            'stops' => $stops,
            'booking' => EventTransportBooking::query()->where('registration_id', $registration->id)->with(['region', 'bus.managers.user:id,name', 'stop.point'])->first(),
        ];
    }

    public function saveTransport(Request $request, FoundationEvent $event, EventTransport $transport): RedirectResponse
    {
        abort_unless($event->status === 'published', 404);
        $registration = $event->registrations()->where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'region_id' => ['required', 'integer'],
            'mode' => ['required', Rule::in(array_keys(EventTransportBooking::MODES))],
            'stop_id' => ['nullable', 'integer', 'required_if:mode,bus'],
        ], [
            'region_id.required' => 'Choisissez votre région de départ.',
            'stop_id.required_if' => 'Choisissez votre bus et votre point de départ.',
        ]);

        try {
            $transport->book($registration, (int) $data['region_id'], $data['mode'], isset($data['stop_id']) ? (int) $data['stop_id'] : null);
        } catch (EventRegistrationException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Votre transport est enregistré.');
    }

    public function register(Request $request, FoundationEvent $event, EventRegistrar $registrar): RedirectResponse
    {
        abort_unless($event->status === 'published', 404);

        $data = $request->validate(['release_id' => ['nullable', 'integer']]);

        try {
            $registration = $registrar->register($event, $request->user(), $data['release_id'] ?? null);
        } catch (EventRegistrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = match ($registration->status) {
            'confirmed' => 'Votre inscription est confirmée.',
            'waitlisted' => 'L\'événement est complet : vous êtes sur liste d\'attente.',
            default => (float) $registration->amount_due > 0
                ? 'Inscription enregistrée. Réglez le montant dû pour la confirmer.'
                : 'Inscription enregistrée, en attente de validation.',
        };

        return back()->with('status', $message);
    }

    public function cancel(Request $request, FoundationEvent $event, EventRegistrar $registrar): RedirectResponse
    {
        $registration = $event->registrations()->where('user_id', $request->user()->id)->firstOrFail();

        if ($registration->status !== 'cancelled') {
            $registrar->cancel($registration);
        }

        return back()->with('status', 'Votre inscription est annulée.');
    }

    public function pay(Request $request, FoundationEvent $event, EventPayments $payments): RedirectResponse
    {
        $registration = $event->registrations()->where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(EventPayment::METHODS))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:6144'],
        ]);

        try {
            $payments->submit($registration, $request->user(), $data['method'], (float) $data['amount'], $data['reference'] ?? null, $request->file('proof'));
        } catch (EventRegistrationException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Paiement envoyé : il sera vérifié par l\'équipe d\'organisation.');
    }

    public function registerSession(Request $request, FoundationEvent $event, EventSession $session, EventRegistrar $registrar): RedirectResponse
    {
        abort_unless($session->event_id === $event->id, 404);
        $registration = $event->registrations()->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $registrar->registerSession($session, $registration);
        } catch (EventRegistrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Vous êtes inscrit(e) à « '.$session->title.' ».');
    }

    public function cancelSession(Request $request, FoundationEvent $event, EventSession $session, EventRegistrar $registrar): RedirectResponse
    {
        abort_unless($session->event_id === $event->id, 404);
        $registration = $event->registrations()->where('user_id', $request->user()->id)->firstOrFail();

        $registrar->cancelSession($session, $registration);

        return back()->with('status', 'Inscription à l\'étape annulée.');
    }

    private function registrationOpen(FoundationEvent $event): bool
    {
        $now = now();

        return $event->status === 'published'
            && ($event->registration_starts_at === null || $event->registration_starts_at->lte($now))
            && ($event->registration_ends_at === null || $event->registration_ends_at->gte($now))
            && ($event->ends_at ?? $event->starts_at)->gte($now);
    }
}
