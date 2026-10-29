<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademySubscription extends Model
{
    protected $fillable = ['user_id', 'plan_id', 'order_id', 'starts_at', 'ends_at'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AcademyPlan::class, 'plan_id');
    }
}
