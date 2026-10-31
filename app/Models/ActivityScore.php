<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityScore extends Model
{
    protected $fillable = ['activity_id', 'criterion_id', 'evaluator_id', 'points', 'comment'];

    protected $casts = ['points' => 'float'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(ChallengeCriterion::class, 'criterion_id');
    }
}
