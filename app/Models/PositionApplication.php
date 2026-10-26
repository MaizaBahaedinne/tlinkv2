<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Candidature à un poste de l'organigramme, déposée via le lien d'inscription du poste.
 * Elle n'attribue aucun droit tant qu'un administrateur ne l'a pas acceptée.
 */
class PositionApplication extends Model
{
    public const STATUSES = ['pending' => 'En attente', 'approved' => 'Acceptée', 'rejected' => 'Refusée'];

    protected $fillable = ['node_id', 'user_id', 'status', 'message', 'decided_by', 'decided_at', 'decision_note'];

    protected $casts = ['decided_at' => 'datetime'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'node_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
