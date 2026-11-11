<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Carte de membre : une par adhésion validée et par mandat. Elle porte le QR code (rotatif) utilisé pour pointer.
 */
class MemberCard extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_EXPIRED => 'Expirée',
        self::STATUS_SUSPENDED => 'Suspendue',
    ];

    /** Alphabet sans caractères ambigus (0/O, 1/I) : le numéro se lit et se recopie sans erreur. */
    private const NUMBER_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = ['user_id', 'membership_id', 'mandate_id', 'number', 'secret', 'status', 'issued_at', 'expires_at'];

    protected $hidden = ['secret'];

    protected $casts = [
        'secret' => 'encrypted',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(Mandate::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(CardScan::class);
    }

    /** Active et non échue : seule une carte valide permet de pointer. */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public static function newNumber(): string
    {
        do {
            $raw = '';

            for ($i = 0; $i < 8; $i++) {
                $raw .= self::NUMBER_ALPHABET[random_int(0, strlen(self::NUMBER_ALPHABET) - 1)];
            }

            $number = 'TL-'.substr($raw, 0, 4).'-'.substr($raw, 4);
        } while (self::query()->where('number', $number)->exists());

        return $number;
    }

    public static function newSecret(): string
    {
        return Str::random(40);
    }
}
