<?php

namespace App\Models;

use App\Support\MarketingEntities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande d'accès aux comptes marketing d'une entité. Le demandeur (poste habilité de l'entité) peut agir pour un
 * autre membre de l'entité, le bénéficiaire. Le digital manager de la Fondation valide ou refuse ; après validation le
 * bénéficiaire signe la charte d'usage des réseaux sociaux, puis les mots de passe lui sont remis et un portfolio Meta lui est attribué.
 */
class MarketingAccessRequest extends Model
{
    public const STATUSES = [
        'pending' => 'En attente de validation',
        'approved' => 'Validée : charte à signer',
        'active' => 'Accès actif',
        'refused' => 'Refusée',
        'revoked' => 'Accès retiré',
        'cancelled' => 'Annulée',
    ];

    public const TONES = ['pending' => 'warning', 'approved' => 'info', 'active' => 'success', 'refused' => 'danger', 'revoked' => 'danger', 'cancelled' => 'neutral'];

    /** Plateformes dont on peut demander l'accès. */
    public const PLATFORMS = ['facebook' => 'Facebook', 'instagram' => 'Instagram'];

    protected $fillable = [
        'club_id', 'scope', 'requester_id', 'beneficiary_id', 'platforms', 'reason', 'facebook_profile_url', 'phone', 'identity_verified',
        'status', 'reviewed_by', 'reviewed_at', 'review_note', 'charter_version', 'charter_text', 'charter_signed_name', 'charter_signed_ip',
        'charter_signed_at', 'meta_portfolio', 'meta_assigned_at', 'meta_assigned_by', 'revoked_at', 'revoked_by',
    ];

    protected $casts = [
        'platforms' => 'array',
        'identity_verified' => 'boolean',
        'reviewed_at' => 'datetime',
        'charter_signed_at' => 'datetime',
        'meta_assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'club_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function entityKey(): string
    {
        return MarketingEntities::keyOf($this->club_id, $this->scope);
    }

    public function entityLabel(): string
    {
        return MarketingEntities::label($this->club_id, $this->scope);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function tone(): string
    {
        return self::TONES[$this->status] ?? 'neutral';
    }

    public function platformLabels(): string
    {
        return collect((array) $this->platforms)->map(fn (string $key): string => self::PLATFORMS[$key] ?? $key)->implode(', ');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Comptes (pages) de l'entité auxquels la demande donne accès. */
    public function accounts()
    {
        return MarketingAccount::query()
            ->whereIn('platform', (array) $this->platforms)
            ->when($this->club_id !== null, fn ($query) => $query->where('club_id', $this->club_id))
            ->when($this->club_id === null, fn ($query) => $query->whereNull('club_id')->where('scope', $this->scope ?: 'foundation'))
            ->orderBy('platform')->orderBy('id');
    }
}
