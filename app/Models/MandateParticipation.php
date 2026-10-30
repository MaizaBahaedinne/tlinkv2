<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participation d'un membre à un ancien mandat (partie rejointe et poste de membre occupé),
 * déclarée par le membre et acceptée directement : elle ne donne aucun droit.
 */
class MandateParticipation extends Model
{
    protected $fillable = ['user_id', 'mandate_id', 'scope', 'node_id', 'position_title'];

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
