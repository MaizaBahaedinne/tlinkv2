<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\EventRegistration;
use App\Models\EventPayment;
use App\Models\FoundationEvent;
use App\Support\Events\EventPayments;
use App\Support\Events\EventRegistrationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Paiements d'un événement : moyens acceptés, enregistrement, validation / rejet, preuves. */
class EventPaymentController extends EventManageController
{
    public function index(Request $request, FoundationEvent $event): View
    {
        $this->allow($request, $event, 'registrations');

        $config = $event->payment_methods;

        return $this->render($request, $event, 'payments', 'admin.events.manage.payments', [
            'payments' => $event->payments()->with(['registration.user:id,name,email', 'submitter:id,name', 'validator:id,name'])->get(),
            'owing' => $event->registrations()
                ->with('user:id,name,email')
                ->where('amount_due', '>', 0)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->where('payment_status', '!=', 'paid')
                ->get(),
            'methods' => EventPayment::METHODS,
            'config' => is_array($config) ? $config : null,
            'enabled' => EventPayments::enabledMethods($event),
        ]);
    }

    public function updateMethods(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'payments_settings');

        $data = $request->validate([
            'methods' => ['nullable', 'array'],
            'methods.*.enabled' => ['nullable', 'boolean'],
            'methods.*.instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $config = [];

        foreach (array_keys(EventPayment::METHODS) as $method) {
            $config[$method] = [
                'enabled' => (bool) ($data['methods'][$method]['enabled'] ?? false),
                'instructions' => trim((string) ($data['methods'][$method]['instructions'] ?? '')),
            ];
        }

        $event->update(['payment_methods' => $config]);
        $this->manageAudit($request, $event, 'payment_methods.updated');

        return back()->with('status', 'Moyens de paiement enregistrés.');
    }

    public function store(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'payments_record');

        $data = $request->validate([
            'registration_id' => ['required', 'integer', Rule::exists('event_registrations', 'id')->where('event_id', $event->id)],
            'method' => ['required', Rule::in(array_keys(EventPayment::METHODS))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:6144'],
        ]);

        $registration = EventRegistration::query()->where('event_id', $event->id)->findOrFail($data['registration_id']);

        try {
            $payment = app(EventPayments::class)->record(
                $registration, $request->user(), $data['method'], (float) $data['amount'],
                $data['reference'] ?? null, $request->file('proof'), $data['note'] ?? null,
            );
        } catch (EventRegistrationException $exception) {
            return back()->withInput()->withErrors(['amount' => $exception->getMessage()]);
        }

        return back()->with('status', $payment->status === 'validated' ? 'Paiement enregistré et validé.' : 'Paiement enregistré, en attente de validation.');
    }

    public function validatePayment(Request $request, FoundationEvent $event, EventPayment $payment): RedirectResponse
    {
        $this->allow($request, $event, 'payments_validate');
        abort_unless($payment->event_id === $event->id, 404);

        try {
            app(EventPayments::class)->validate($payment, $request->user());
        } catch (EventRegistrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Paiement validé.');
    }

    public function reject(Request $request, FoundationEvent $event, EventPayment $payment): RedirectResponse
    {
        $this->allow($request, $event, 'payments_validate');
        abort_unless($payment->event_id === $event->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            app(EventPayments::class)->reject($payment, $request->user(), $data['reason']);
        } catch (EventRegistrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Paiement rejeté.');
    }

    public function proof(Request $request, FoundationEvent $event, EventPayment $payment): StreamedResponse
    {
        $this->allow($request, $event, 'registrations');
        abort_unless($payment->event_id === $event->id && $payment->hasProof(), 404);

        return Storage::disk(EventPayment::PROOF_DISK)->response($payment->proof_path);
    }
}
