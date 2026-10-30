<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participation d'un membre à un ancien mandat (partie rejointe et poste occupé), déclarée par le membre.
 * Simple membre : acceptée directement. Poste de responsabilité : en attente jusqu'à la validation d'un administrateur.
 */
class MandateParticipation extends Model
{
    public const STATUSES = [
        'pending' => ['En attente de validation', 'bg-amber-50 text-amber-700'],
        'approved' => ['Acceptée', 'bg-emerald-50 text-emerald-700'],
        'rejected' => ['Refusée', 'bg-rose-50 text-rose-700'],
    ];

    protected $fillable = ['user_id', 'mandate_id', 'scope', 'node_id', 'position_title', 'status', 'rejection_reason', 'approved_by', 'approved_at'];

    protected $casts = ['approved_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(Mandate::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'node_id');
    }
}
