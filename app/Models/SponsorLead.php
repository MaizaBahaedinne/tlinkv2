<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Piste de sponsoring ou de partenariat menée par un club (ou la Fondation) auprès d'un sponsor du carnet. */
class SponsorLead extends Model
{
    public const KINDS = ['sponsoring' => 'Sponsoring financier', 'in_kind' => 'Soutien en nature', 'partnership' => 'Partenariat'];

    /** Étapes de la piste, dans l'ordre ; « won » et « lost » la clôturent. */
    public const STATUSES = [
        'idea' => 'Idée',
        'contacted' => 'Premier contact',
        'meeting' => 'Rendez-vous',
        'proposal' => 'Dossier envoyé',
        'negotiation' => 'Négociation',
        'won' => 'Obtenu',
        'lost' => 'Perdu',
    ];

    public const CLOSED = ['won', 'lost'];

    protected $fillable = [
        'sponsor_id', 'club_id', 'contact_id', 'title', 'kind', 'amount', 'status', 'next_action', 'next_action_at', 'notes',
        'owner_id', 'created_by', 'closed_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'next_action_at' => 'date', 'closed_at' => 'datetime'];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'club_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(SponsorContact::class, 'contact_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED, true);
    }

    public function isLate(): bool
    {
        return ! $this->isClosed() && $this->next_action_at !== null && $this->next_action_at->isPast() && ! $this->next_action_at->isToday();
    }
}
