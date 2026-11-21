<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Étape du circuit d'un bus : un point de collecte, son heure de départ et l'arrivée estimée à l'événement. */
class EventBusStop extends Model
{
    protected $fillable = ['bus_id', 'point_id', 'sort_order', 'departs_at', 'arrives_at'];

    protected $casts = ['departs_at' => 'datetime', 'arrives_at' => 'datetime'];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(EventBus::class, 'bus_id');
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(EventTransportPoint::class, 'point_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventTransportBooking::class, 'stop_id');
    }
}
