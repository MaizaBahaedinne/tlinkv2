<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityOrganizer extends Model
{
    public const STATUSES = ['pending' => 'Invitation envoyée', 'accepted' => 'Acceptée', 'declined' => 'Refusée'];

    protected $fillable = ['activity_id', 'club_id', 'status', 'responded_at'];

    protected $casts = ['responded_at' => 'datetime'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'club_id');
    }
}
