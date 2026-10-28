<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionCheckpoint extends Model
{
    protected $fillable = ['mission_id', 'title', 'trigger_key', 'target', 'sort_order'];

    protected $casts = [
        'target' => 'integer',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}
