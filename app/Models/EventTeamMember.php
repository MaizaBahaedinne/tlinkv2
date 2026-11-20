<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Membre de l'équipe d'organisation (OC) d'un événement. */
class EventTeamMember extends Model
{
    protected $fillable = ['event_id', 'user_id', 'role', 'notes'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
