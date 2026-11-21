<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Point de départ ou de collecte (avec position GPS facultative). */
class EventTransportPoint extends Model
{
    protected $fillable = ['event_id', 'region_id', 'name', 'city', 'address', 'latitude', 'longitude'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(EventTransportRegion::class, 'region_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(EventBusStop::class, 'point_id');
    }

    public function label(): string
    {
        return $this->name.($this->city ? ' — '.$this->city : '');
    }

    /** Lien d'itinéraire (position GPS, à défaut l'adresse). */
    public function mapsUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return 'https://www.google.com/maps?q='.$this->latitude.','.$this->longitude;
        }

        $query = trim(($this->address ?: '').' '.($this->city ?: ''));

        return $query !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query) : null;
    }
}
