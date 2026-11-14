<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Point de l'ordre du jour d'une réunion, avec sa décision. */
class MeetingAgendaItem extends Model
{
    public const OUTCOMES = [
        'pending' => 'À traiter',
        'info' => 'Pour information',
        'adopted' => 'Adopté',
        'rejected' => 'Rejeté',
        'postponed' => 'Reporté',
    ];

    protected $fillable = ['meeting_id', 'position', 'title', 'description', 'duration_minutes', 'presenter_id', 'outcome', 'decision'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_id');
    }
}
