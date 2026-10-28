<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoundationEvent extends Model
{
    use HasFactory;

    protected $table = 'events';

    protected $fillable = [
        'title',
        'description',
        'cover_image_path',
        'organizer_id',
        'scope',
        'node_id',
        'governorate_id',
        'starts_at',
        'ends_at',
        'location',
        'registration_starts_at',
        'registration_ends_at',
        'capacity',
        'price',
        'approval_required',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_starts_at' => 'datetime',
        'registration_ends_at' => 'datetime',
        'approval_required' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /** URL publique de la cover, ou null. */
    public function coverUrl(): ?string
    {
        return $this->cover_image_path ? asset('storage/'.ltrim($this->cover_image_path, '/')) : null;
    }

    /** « Gratuit » ou « 15 TND » ; null tant que le prix n'est pas renseigné. */
    public function priceLabel(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        if ((float) $this->price <= 0) {
            return __('site.events.free');
        }

        return rtrim(rtrim(number_format((float) $this->price, 2, ',', ' '), '0'), ',').' TND';
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
    }
}
