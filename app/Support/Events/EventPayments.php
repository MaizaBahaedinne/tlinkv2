<?php

namespace App\Support\Events;

use App\Models\AppNotification;
use App\Models\EventPayment;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Paiements d'inscription : dépôt avec preuve, validation / rejet par un validateur distinct du payeur,
 * recalcul du statut de paiement de l'inscription.
 */
final class EventPayments
{
    /**
     * Moyens de paiement actifs de l'événement avec leurs instructions (RIB, numéro D17…).
     * Aucun réglage enregistré : tous les moyens sont proposés sans instruction.
     *
     * @return array<string, string> méthode => instructions
     */
    public static function enabledMethods(FoundationEvent $event): array
    {
        $config = $event->payment_methods;
        $enabled = [];

        foreach (array_keys(EventPayment::METHODS) as $method) {
            if (! is_array($config)) {
                $enabled[$method] = '';

                continue;
            }

            if (! empty($config[$method]['enabled'])) {
                $enabled[$method] = (string) ($config[$method]['instructions'] ?? '');
            }
        }

        return $enabled;
    }

    /** Reste à payer, hors paiements rejetés (les paiements en attente comptent comme engagés). */
    public static function outstanding(EventRegistration $registration): float
    {
        $covered = (float) $registration->payments()->whereIn('status', ['pending', 'validated'])->sum('amount');

        return max(0.0, round((float) $registration->amount_due - $covered, 2));
    }

    /**
     * Dépôt d'un paiement par le membre : une preuve est exigée, le cash se règle auprès de l'équipe.
     *
     * @throws EventRegistrationException
     */
    public function submit(EventRegistration $registration, User $user, string $method, float $amount, ?string $reference, ?UploadedFile $proof): EventPayment
    {
        $event = $registration->event;

        if (! array_key_exists($method, self::enabledMethods($event)) || $method === 'cash') {
            throw new EventRegistrationException('Moyen de paiement non disponible pour le dépôt en ligne.', 422);
        }

        if ($proof === null) {
            throw new EventRegistrationException('Une preuve de paiement est requise.', 422);
        }

        return $this->create($registration, $user, $method, $amount, $reference, $proof, null);
    }

    /**
     * Enregistrement par l'équipe (ex. cash encaissé). Validé d'emblée si l'auteur a le droit de valider
     * et n'est pas le payeur lui-même ; sinon en attente d'un validateur.
     *
     * @throws EventRegistrationException
     */
    public function record(EventRegistration $registration, User $actor, string $method, float $amount, ?string $reference, ?UploadedFile $proof, ?string $note): EventPayment
    {
        $payment = $this->create($registration, $actor, $method, $amount, $reference, $proof, $note);

        if ($actor->id !== $registration->user_id && EventAccess::can($actor, $registration->event, 'payments_validate')) {
            $this->validate($payment, $actor);
        }

        return $payment->refresh();
    }

    /**
     * @throws EventRegistrationException
     */
    public function validate(EventPayment $payment, User $actor): void
    {
        $registration = $payment->registration;
        $this->assertCanDecide($payment, $actor);

        $payment->update([
            'status' => 'validated',
            'validated_by' => $actor->id,
            'validated_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->recompute($registration);
        $this->audit($actor, 'event.payment.validated', $payment);
        $this->notify($registration, 'Paiement validé', 'Votre paiement de '.$this->money((float) $payment->amount).' pour « '.$registration->event->title.' » a été validé.', $payment);
    }

    /**
     * @throws EventRegistrationException
     */
    public function reject(EventPayment $payment, User $actor, string $reason): void
    {
        $registration = $payment->registration;
        $this->assertCanDecide($payment, $actor);

        $payment->update([
            'status' => 'rejected',
            'validated_by' => $actor->id,
            'validated_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->recompute($registration);
        $this->audit($actor, 'event.payment.rejected', $payment);
        $this->notify($registration, 'Paiement rejeté', 'Votre paiement pour « '.$registration->event->title.' » a été rejeté : '.$reason, $payment);
    }

    /** Recalcule le statut de paiement ; un paiement complet confirme une inscription en attente (hors validation manuelle). */
    public function recompute(EventRegistration $registration): void
    {
        $registration->refresh();
        $due = (float) $registration->amount_due;

        if ($due <= 0) {
            $registration->update(['payment_status' => 'free']);

            return;
        }

        $validated = (float) $registration->payments()->where('status', 'validated')->sum('amount');

        if ($validated + 0.001 >= $due) {
            $status = 'paid';
        } elseif ($registration->payments()->where('status', 'pending')->exists()) {
            $status = 'pending';
        } else {
            $status = 'unpaid';
        }

        $update = ['payment_status' => $status];

        if ($status === 'paid' && $registration->status === 'pending' && ! $registration->event->approval_required) {
            $update['status'] = 'confirmed';
        }

        $registration->update($update);
    }

    private function create(EventRegistration $registration, User $actor, string $method, float $amount, ?string $reference, ?UploadedFile $proof, ?string $note): EventPayment
    {
        if (! array_key_exists($method, EventPayment::METHODS)) {
            throw new EventRegistrationException('Moyen de paiement inconnu.', 422);
        }

        if (in_array($registration->status, ['cancelled', 'rejected'], true)) {
            throw new EventRegistrationException("L'inscription n'est plus active.", 422);
        }

        $outstanding = self::outstanding($registration);

        if ($amount <= 0 || $amount > $outstanding + 0.001) {
            throw new EventRegistrationException('Montant invalide : il reste '.$this->money($outstanding).' à régler.', 422);
        }

        return DB::transaction(function () use ($registration, $actor, $method, $amount, $reference, $proof, $note): EventPayment {
            $payment = EventPayment::query()->create([
                'event_id' => $registration->event_id,
                'registration_id' => $registration->id,
                'method' => $method,
                'amount' => round($amount, 2),
                'reference' => $reference,
                'proof_path' => $proof?->store('event-payments/'.$registration->event_id, EventPayment::PROOF_DISK),
                'status' => 'pending',
                'submitted_by' => $actor->id,
                'note' => $note,
            ]);

            $this->recompute($registration);
            $this->audit($actor, 'event.payment.submitted', $payment);

            return $payment;
        });
    }

    /**
     * @throws EventRegistrationException
     */
    private function assertCanDecide(EventPayment $payment, User $actor): void
    {
        if ($payment->status !== 'pending') {
            throw new EventRegistrationException('Ce paiement a déjà été traité.', 409);
        }

        if ($payment->registration->user_id === $actor->id) {
            throw new EventRegistrationException('Vous ne pouvez pas valider votre propre paiement.', 403);
        }
    }

    private function audit(User $actor, string $action, EventPayment $payment): void
    {
        AuditLogger::record(
            actorId: $actor->id,
            action: $action,
            targetType: EventPayment::class,
            targetId: $payment->id,
            metadata: ['event_id' => $payment->event_id, 'registration_id' => $payment->registration_id, 'method' => $payment->method, 'amount' => (string) $payment->amount],
            ip: request()?->ip(),
        );
    }

    private function notify(EventRegistration $registration, string $title, string $body, EventPayment $payment): void
    {
        AppNotification::query()->create([
            'user_id' => $registration->user_id,
            'type' => 'event.payment',
            'title' => $title,
            'body' => $body,
            'data' => ['event_id' => $registration->event_id, 'registration_id' => $registration->id, 'payment_id' => $payment->id, 'status' => $payment->status],
        ]);
    }

    private function money(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',').' TND';
    }

    /** Supprime la preuve d'un paiement (nettoyage). */
    public static function deleteProof(EventPayment $payment): void
    {
        if ($payment->proof_path) {
            Storage::disk(EventPayment::PROOF_DISK)->delete($payment->proof_path);
        }
    }
}
