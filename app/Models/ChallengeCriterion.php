<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChallengeCriterion extends Model
{
    protected $fillable = ['challenge_id', 'kind', 'label', 'description', 'max_points', 'sort_order'];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
