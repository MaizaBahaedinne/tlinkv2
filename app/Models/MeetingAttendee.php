<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Invité d'une réunion : sa réponse à l'invitation et sa présence constatée. */
class MeetingAttendee extends Model
{
    public const RESPONSES = ['pending' => 'Sans réponse', 'accepted' => 'Sera présent', 'declined' => 'Ne viendra pas'];

    public const ATTENDANCE = ['present' => 'Présent', 'absent' => 'Absent', 'excused' => 'Excusé'];

    protected $fillable = ['meeting_id', 'user_id', 'response', 'attendance', 'invited_at', 'responded_at'];

    protected $casts = ['invited_at' => 'datetime', 'responded_at' => 'datetime'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
