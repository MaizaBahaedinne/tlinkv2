<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReportPartner extends Model
{
    protected $fillable = ['report_id', 'name', 'nature', 'amount', 'description'];

    protected $casts = ['amount' => 'float'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ActivityReport::class, 'report_id');
    }

    public function natureLabel(): string
    {
        return ActivityReport::NATURES[$this->nature] ?? $this->nature;
    }
}
