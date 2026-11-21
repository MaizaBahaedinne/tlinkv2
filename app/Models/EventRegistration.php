<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventRegistration extends Model
{
    use HasFactory;

    /** Statuts qui occupent une place. */
    public const SEAT_HOLDING = ['pending', 'confirmed', 'attended'];

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'registered_at',
        'release_id',
        'amount_due',
        'payment_status',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'amount_due' => 'decimal:2',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(EventRelease::class, 'release_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EventPayment::class, 'registration_id')->latest('id');
    }

    public function sessionRegistrations(): HasMany
    {
        return $this->hasMany(EventSessionRegistration::class, 'registration_id');
    }

    public function companions(): HasMany
    {
        return $this->hasMany(EventRegistrationCompanion::class, 'registration_id')->orderBy('id');
    }

    public function transportBooking(): HasOne
    {
        return $this->hasOne(EventTransportBooking::class, 'registration_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
