<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Réunion d'un club ou de la Fondation : date, lieu, sujet, durée, ordre du jour, invités, présences et décisions. */
class Meeting extends Model
{
    public const STATUSES = ['planned' => 'Planifiée', 'held' => 'Tenue', 'cancelled' => 'Annulée'];

    protected $fillable = [
        'club_id', 'title', 'subject', 'starts_at', 'duration_minutes', 'location', 'online_url', 'status', 'organizer_id',
        'guests', 'minutes', 'invitations_sent_at', 'created_by',
    ];

    protected $casts = ['starts_at' => 'datetime', 'invitations_sent_at' => 'datetime', 'duration_minutes' => 'integer'];

    public function club(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'club_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('position')->orderBy('id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class)->with('user:id,name,email')->orderBy('id');
    }

    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    public function isOpen(): bool
    {
        return $this->status === 'planned';
    }
}
