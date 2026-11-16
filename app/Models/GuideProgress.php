<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Avancement d'un membre dans un guide : dernière étape lue et date de fin. */
class GuideProgress extends Model
{
    protected $table = 'guide_progress';

    protected $fillable = ['guide_id', 'user_id', 'last_step', 'completed_at'];

    protected $casts = [
        'last_step' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
