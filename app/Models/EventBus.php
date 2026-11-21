<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bus d'un événement : un circuit (points de collecte) jusqu'à l'adresse de l'événement. */
class EventBus extends Model
{
    protected $fillable = ['event_id', 'name', 'plate', 'capacity', 'notes', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(EventBusStop::class, 'bus_id')->orderBy('sort_order')->orderBy('id');
    }

    public function managers(): HasMany
    {
        return $this->hasMany(EventBusManager::class, 'bus_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventTransportBooking::class, 'bus_id');
    }

    /** Places occupées (inscriptions actives uniquement). */
    public function taken(): int
    {
        return $this->bookings()->active()->count();
    }

    public function remaining(): int
    {
        return max(0, $this->capacity - $this->taken());
    }
}
