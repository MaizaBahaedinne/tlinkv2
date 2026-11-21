<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Choix de transport d'un inscrit : véhicule personnel (non pris en charge) ou bus depuis un point de départ. */
class EventTransportBooking extends Model
{
    public const MODES = [
        'own' => 'Départ motorisé personnel (non pris en charge)',
        'bus' => 'Bus de l’organisation',
    ];

    public const BOARDING = [
        'pending' => 'Pas encore pointé',
        'present' => 'Présent',
        'absent' => 'Absent',
    ];

    protected $fillable = ['event_id', 'registration_id', 'user_id', 'region_id', 'point_id', 'mode', 'bus_id', 'stop_id', 'boarding', 'marked_by'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(EventTransportRegion::class, 'region_id');
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(EventTransportPoint::class, 'point_id');
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(EventBus::class, 'bus_id');
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(EventBusStop::class, 'stop_id');
    }

    /** Uniquement les inscriptions qui comptent encore (ni annulées, ni refusées, ni en liste d'attente). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('registration', fn (Builder $registration) => $registration->whereIn('status', ['pending', 'confirmed', 'attended']));
    }
}
