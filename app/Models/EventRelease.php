<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une « release » (tranche tarifaire) d'inscription à un événement. */
class EventRelease extends Model
{
    protected $fillable = [
        'event_id', 'name', 'description', 'price', 'quantity',
        'opens_at', 'closes_at', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'release_id');
    }

    /** Ouverte maintenant (active et dans sa fenêtre de dates) — hors quota. */
    public function isOpen(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        return ($this->opens_at === null || $this->opens_at->lte($now))
            && ($this->closes_at === null || $this->closes_at->gte($now));
    }

    /** Places occupées (inscriptions qui comptent dans le quota). */
    public function taken(): int
    {
        return $this->registrations()
            ->whereIn('status', EventRegistration::SEAT_HOLDING)
            ->count();
    }

    public function remaining(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->taken());
    }

    public function priceLabel(): string
    {
        if ((float) $this->price <= 0) {
            return 'Gratuit';
        }

        return rtrim(rtrim(number_format((float) $this->price, 2, ',', ' '), '0'), ',').' TND';
    }
}
