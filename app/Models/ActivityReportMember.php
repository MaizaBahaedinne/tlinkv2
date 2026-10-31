<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReportMember extends Model
{
    protected $fillable = ['report_id', 'user_id', 'role', 'implication', 'comment'];

    protected $casts = ['implication' => 'integer'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ActivityReport::class, 'report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
