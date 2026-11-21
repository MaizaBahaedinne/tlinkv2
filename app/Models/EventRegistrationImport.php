<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Inscrit importé (nom, prénom, téléphone) qui doit encore compléter sa fiche. */
class EventRegistrationImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'event_id', 'release_id', 'first_name', 'last_name', 'phone', 'phone_key',
        'status', 'user_id', 'registration_id', 'declared', 'completed_at',
    ];

    protected $casts = [
        'declared' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Clé de recherche d'un numéro : ses 8 derniers chiffres (numéro tunisien), quelle que soit la mise en forme.
     * Null si le numéro est trop court pour être reconnu.
     */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 8 ? substr($digits, -8) : null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(EventRelease::class, 'release_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
