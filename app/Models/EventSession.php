<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une entrée du programme d'un événement (cérémonie, panel, atelier…). */
class EventSession extends Model
{
    public const TYPES = [
        'opening' => "Cérémonie d'ouverture",
        'panel' => 'Panel de discussion',
        'training' => 'Formation',
        'workshop' => 'Atelier',
        'workshop_lab' => 'Workshop',
        'team_building' => 'Team building',
        'forum' => 'Forum',
        'job_fair' => 'Job fair',
        'party' => 'Soirée',
        'closing' => 'Cérémonie de clôture',
        'other' => 'Autre',
    ];

    public const VENUE_MODES = [
        'event' => "Dans le lieu de l'événement (salle / espace)",
        'external' => 'Lieu externe',
    ];

    protected $fillable = [
        'event_id', 'type', 'title', 'description', 'starts_at', 'ends_at',
        'venue_mode', 'venue_name', 'venue_address', 'room',
        'requires_registration', 'capacity', 'sort_order',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'requires_registration' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(EventSpeaker::class, 'event_session_speaker', 'session_id', 'speaker_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventSessionRegistration::class, 'session_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES['other'];
    }

    /** Libellé du lieu : lieu global de l'événement (+ salle) ou lieu externe. */
    public function venueLabel(): ?string
    {
        if ($this->venue_mode === 'external') {
            return trim(($this->venue_name ?: '').($this->venue_address ? ' — '.$this->venue_address : '')) ?: null;
        }

        $base = $this->event?->location ?: null;

        if ($this->room) {
            return trim(($base ? $base.' — ' : '').$this->room);
        }

        return $base;
    }

    public function taken(): int
    {
        return $this->registrations()->where('status', 'registered')->count();
    }

    public function remaining(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->taken());
    }
}
