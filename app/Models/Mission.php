<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mission extends Model
{
    protected $fillable = ['badge_id', 'title', 'description', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(MissionCheckpoint::class)->orderBy('sort_order')->orderBy('id');
    }
}
