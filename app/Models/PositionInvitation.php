<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation envoyée par e-mail à une personne pour candidater à un poste vacant de l'organigramme.
 * Le lien est personnel : il crée le compte puis dépose la candidature, qui reste à valider par un administrateur.
 */
class PositionInvitation extends Model
{
    public const VALID_DAYS = 14;

    public const STATUSES = ['pending' => 'En attente', 'accepted' => 'A candidaté', 'expired' => 'Expirée', 'revoked' => 'Révoquée'];

    protected $fillable = ['node_id', 'first_name', 'last_name', 'email', 'token_hash', 'invited_by', 'user_id', 'expires_at', 'last_sent_at', 'send_count', 'accepted_at', 'revoked_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'send_count' => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'node_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findByToken(string $plain): ?self
    {
        return static::query()->where('token_hash', self::hashToken($plain))->first();
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** @return 'pending'|'accepted'|'expired'|'revoked' */
    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    public function isOpen(): bool
    {
        return $this->status() === 'pending';
    }
}
