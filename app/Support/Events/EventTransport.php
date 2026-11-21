<?php

namespace App\Support\Events;

use App\Models\EventBus;
use App\Models\EventBusStop;
use App\Models\EventRegistration;
use App\Models\EventTransportBooking;
use App\Models\EventTransportPoint;
use App\Models\EventTransportRegion;
use App\Models\AppNotification;
use Illuminate\Support\Facades\DB;

/**
 * Choix de transport d'un inscrit : région de départ, puis véhicule personnel (non pris en charge)
 * ou bus de l'organisation depuis un point de départ / de collecte (avec contrôle des places).
 */
final class EventTransport
{
    /**
     * @throws EventRegistrationException
     */
    public function book(EventRegistration $registration, ?int $regionId, string $mode, ?int $stopId): EventTransportBooking
    {
        $event = $registration->event;

        if (! in_array($registration->status, ['pending', 'confirmed', 'attended'], true)) {
            throw new EventRegistrationException('Le transport est réservé aux inscriptions actives.', 422);
        }

        if ($event->starts_at !== null && $event->starts_at->isPast()) {
            throw new EventRegistrationException('L’événement a commencé : le transport ne peut plus être modifié.', 422);
        }

        $region = EventTransportRegion::query()->where('event_id', $event->id)->find($regionId);

        if ($region === null) {
            throw new EventRegistrationException('Choisissez votre région de départ.', 422);
        }

        return DB::transaction(function () use ($registration, $event, $region, $mode, $stopId): EventTransportBooking {
            $existing = EventTransportBooking::query()->where('registration_id', $registration->id)->first();

            if ($existing !== null && $existing->boarding !== 'pending') {
                throw new EventRegistrationException('Votre présence a déjà été pointée : le transport ne peut plus être modifié.', 422);
            }

            $busId = null;
            $stopKey = null;

            if ($mode === 'bus') {
                $stop = EventBusStop::query()
                    ->with('point')
                    ->whereKey($stopId)
                    ->whereHas('bus', fn ($query) => $query->where('event_id', $event->id))
                    ->first();

                if ($stop === null || $stop->point->region_id !== $region->id) {
                    throw new EventRegistrationException('Choisissez un point de départ de votre région.', 422);
                }

                // Verrou sur le bus : sérialise les réservations concurrentes (places).
                $bus = EventBus::query()->whereKey($stop->bus_id)->lockForUpdate()->firstOrFail();
                $taken = $bus->bookings()->active()->where('registration_id', '!=', $registration->id)->count();

                if ($taken >= $bus->capacity) {
                    throw new EventRegistrationException('Ce bus est complet : choisissez un autre bus ou point de départ.', 409);
                }

                $busId = $bus->id;
                $stopKey = $stop->id;
            }

            $attributes = [
                'event_id' => $event->id,
                'user_id' => $registration->user_id,
                'region_id' => $region->id,
                'point_id' => $mode === 'bus' ? $stop->point_id : null,
                'mode' => $mode === 'bus' ? 'bus' : 'own',
                'bus_id' => $busId,
                'stop_id' => $stopKey,
            ];

            return EventTransportBooking::query()->updateOrCreate(['registration_id' => $registration->id], $attributes);
        });
    }

    /**
     * Choix de l'inscrit : région puis point de départ seulement. Le bus est affecté ensuite par l'équipe transport.
     *
     * @throws EventRegistrationException
     */
    public function bookPoint(EventRegistration $registration, ?int $regionId, string $mode, ?int $pointId): EventTransportBooking
    {
        $event = $registration->event;

        if (! in_array($registration->status, ['pending', 'confirmed', 'attended'], true)) {
            throw new EventRegistrationException('Le transport est réservé aux inscriptions actives.', 422);
        }

        if ($event->starts_at !== null && $event->starts_at->isPast()) {
            throw new EventRegistrationException('L’événement a commencé : le transport ne peut plus être modifié.', 422);
        }

        $region = EventTransportRegion::query()->where('event_id', $event->id)->find($regionId);

        if ($region === null) {
            throw new EventRegistrationException('Choisissez votre région de départ.', 422);
        }

        $point = null;

        if ($mode === 'bus') {
            $point = EventTransportPoint::query()->where('event_id', $event->id)->where('region_id', $region->id)->find($pointId);

            if ($point === null) {
                throw new EventRegistrationException('Choisissez un point de départ de votre région.', 422);
            }
        }

        return EventTransportBooking::query()->updateOrCreate(['registration_id' => $registration->id], [
            'event_id' => $event->id,
            'user_id' => $registration->user_id,
            'region_id' => $region->id,
            'point_id' => $point?->id,
            'mode' => $point !== null ? 'bus' : 'own',
            'bus_id' => null,
            'stop_id' => null,
        ]);
    }

    /** Prévient tous les passagers actifs d'un bus. */
    public function notifyPassengers(EventBus $bus, string $title, string $body): int
    {
        $userIds = $bus->bookings()->active()->pluck('user_id')->unique();

        foreach ($userIds as $userId) {
            AppNotification::query()->create([
                'user_id' => $userId,
                'type' => 'event.transport',
                'title' => $title,
                'body' => $body,
                'data' => ['event_id' => $bus->event_id, 'bus_id' => $bus->id],
            ]);
        }

        return $userIds->count();
    }
}
