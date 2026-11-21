<?php

namespace App\Support\Events;

use App\Models\AppNotification;
use App\Models\EventRegistration;
use App\Models\EventRelease;
use App\Models\EventSession;
use App\Models\EventSessionRegistration;
use App\Models\FoundationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Inscription à un événement : choix de la release, quota, liste d'attente, montant dû, étapes du programme.
 */
final class EventRegistrar
{
    /**
     * @throws EventRegistrationException
     */
    public function register(FoundationEvent $event, User $user, ?int $releaseId = null): EventRegistration
    {
        $now = now();

        if ($event->registration_starts_at !== null && $event->registration_starts_at->gt($now)) {
            throw new EventRegistrationException("Les inscriptions ne sont pas encore ouvertes.", 422);
        }

        if ($event->registration_ends_at !== null && $event->registration_ends_at->lt($now)) {
            throw new EventRegistrationException('Les inscriptions sont closes.', 422);
        }

        return DB::transaction(function () use ($event, $user, $releaseId): EventRegistration {
            // Verrou sur l'événement : sérialise les inscriptions concurrentes (quotas).
            FoundationEvent::query()->whereKey($event->id)->lockForUpdate()->first();

            $existing = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing !== null && $existing->status !== 'cancelled') {
                throw new EventRegistrationException('Inscription deja existante.', 409);
            }

            $release = $this->resolveRelease($event, $releaseId);

            $amount = $release !== null ? (float) $release->price : (float) ($event->price ?? 0);

            $seatsTaken = EventRegistration::query()
                ->where('event_id', $event->id)
                ->whereIn('status', EventRegistration::SEAT_HOLDING)
                ->count();

            if ($event->capacity !== null && $seatsTaken >= $event->capacity) {
                $status = 'waitlisted';
            } elseif ($event->approval_required || $amount > 0) {
                $status = 'pending';
            } else {
                $status = 'confirmed';
            }

            $attributes = [
                'status' => $status,
                'registered_at' => now(),
                'release_id' => $release?->id,
                'amount_due' => $amount,
                'payment_status' => $amount > 0 ? 'unpaid' : 'free',
            ];

            if ($existing !== null) {
                $existing->transportBooking()->delete();
                $existing->update($attributes);

                return $existing->refresh();
            }

            return EventRegistration::query()->create($attributes + ['event_id' => $event->id, 'user_id' => $user->id]);
        });
    }

    /**
     * Inscription d'une personne déjà inscrite hors de T.Link (liste importée) : elle a sa place dans la release prévue,
     * sans contrôle de quota ni de période d'ouverture. L'inscription attend le paiement (ou la validation) comme les autres.
     */
    public function registerImported(FoundationEvent $event, User $user, ?EventRelease $release): EventRegistration
    {
        $amount = $release !== null ? (float) $release->price : (float) ($event->price ?? 0);

        $attributes = [
            'status' => $event->approval_required || $amount > 0 ? 'pending' : 'confirmed',
            'registered_at' => now(),
            'release_id' => $release?->id,
            'amount_due' => $amount,
            'payment_status' => $amount > 0 ? 'unpaid' : 'free',
        ];

        $existing = EventRegistration::query()->where('event_id', $event->id)->where('user_id', $user->id)->first();

        if ($existing !== null && $existing->status !== 'cancelled') {
            return $existing;
        }

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        return EventRegistration::query()->create($attributes + ['event_id' => $event->id, 'user_id' => $user->id]);
    }

    /**
     * Annule une inscription (et ses étapes), puis promeut la première personne de la liste d'attente.
     */
    public function cancel(EventRegistration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            $registration->update(['status' => 'cancelled']);
            $registration->sessionRegistrations()->update(['status' => 'cancelled']);
            $registration->transportBooking()->delete();
        });

        $this->promoteWaitlist($registration->event);
    }

    /** Promeut la liste d'attente tant qu'il reste des places. */
    public function promoteWaitlist(FoundationEvent $event): void
    {
        if ($event->capacity === null) {
            return;
        }

        $seats = EventRegistration::query()
            ->where('event_id', $event->id)
            ->whereIn('status', EventRegistration::SEAT_HOLDING)
            ->count();

        $free = $event->capacity - $seats;

        if ($free <= 0) {
            return;
        }

        $waiting = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('status', 'waitlisted')
            ->orderBy('registered_at')
            ->orderBy('id')
            ->limit($free)
            ->get();

        foreach ($waiting as $registration) {
            $needsAction = $event->approval_required || (float) $registration->amount_due > 0;
            $registration->update(['status' => $needsAction ? 'pending' : 'confirmed']);

            AppNotification::query()->create([
                'user_id' => $registration->user_id,
                'type' => 'event.registration',
                'title' => 'Une place s\'est libérée',
                'body' => 'Vous avez été promu(e) de la liste d\'attente pour « '.$event->title.' ».',
                'data' => ['event_id' => $event->id, 'registration_id' => $registration->id, 'status' => $registration->status],
            ]);
        }
    }

    /**
     * Inscription à une étape du programme (réservée aux inscrits confirmés ou en attente de paiement/validation).
     *
     * @throws EventRegistrationException
     */
    public function registerSession(EventSession $session, EventRegistration $registration): EventSessionRegistration
    {
        if (! $session->requires_registration) {
            throw new EventRegistrationException('Cette étape ne nécessite pas d\'inscription.', 422);
        }

        if (! in_array($registration->status, ['confirmed', 'pending'], true)) {
            throw new EventRegistrationException('Votre inscription à l\'événement n\'est pas active.', 422);
        }

        return DB::transaction(function () use ($session, $registration): EventSessionRegistration {
            EventSession::query()->whereKey($session->id)->lockForUpdate()->first();

            $existing = EventSessionRegistration::query()
                ->where('session_id', $session->id)
                ->where('user_id', $registration->user_id)
                ->first();

            if ($existing !== null && $existing->status === 'registered') {
                throw new EventRegistrationException('Vous êtes déjà inscrit(e) à cette étape.', 409);
            }

            if ($session->capacity !== null && $session->taken() >= $session->capacity) {
                throw new EventRegistrationException('Cette étape est complète.', 409);
            }

            if ($existing !== null) {
                $existing->update(['status' => 'registered', 'registration_id' => $registration->id]);

                return $existing;
            }

            return EventSessionRegistration::query()->create([
                'session_id' => $session->id,
                'registration_id' => $registration->id,
                'user_id' => $registration->user_id,
                'status' => 'registered',
            ]);
        });
    }

    public function cancelSession(EventSession $session, EventRegistration $registration): void
    {
        EventSessionRegistration::query()
            ->where('session_id', $session->id)
            ->where('user_id', $registration->user_id)
            ->update(['status' => 'cancelled']);
    }

    /**
     * @throws EventRegistrationException
     */
    private function resolveRelease(FoundationEvent $event, ?int $releaseId): ?EventRelease
    {
        $hasReleases = $event->releases()->where('is_active', true)->exists();

        if ($releaseId === null) {
            if ($hasReleases) {
                throw new EventRegistrationException('Choisissez une release (tarif) pour vous inscrire.', 422);
            }

            return null;
        }

        $release = EventRelease::query()->where('event_id', $event->id)->whereKey($releaseId)->first();

        if ($release === null) {
            throw new EventRegistrationException('Release introuvable pour cet événement.', 422);
        }

        if (! $release->isOpen()) {
            throw new EventRegistrationException("Cette release n'est pas ouverte.", 422);
        }

        if ($release->remaining() === 0) {
            throw new EventRegistrationException('Cette release est épuisée.', 409);
        }

        return $release;
    }
}
