<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Région de départ proposée aux inscrits, définie par l'équipe d'organisation. */
class EventTransportRegion extends Model
{
    protected $fillable = ['event_id', 'name', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(EventTransportPoint::class, 'region_id')->orderBy('city')->orderBy('name');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventTransportBooking::class, 'region_id');
    }
}
