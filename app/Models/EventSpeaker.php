<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Intervenant : speaker, formateur, invité d'honneur… (Tunivisions ou externe). */
class EventSpeaker extends Model
{
    public const KINDS = [
        'speaker' => 'Speaker',
        'trainer' => 'Formateur',
        'guest_of_honor' => "Invité d'honneur",
        'moderator' => 'Modérateur',
        'host' => 'Animateur',
    ];

    public const ORIGINS = [
        'tunivisions' => 'Tunivisions',
        'external' => 'Externe',
    ];

    protected $fillable = [
        'event_id', 'name', 'kind', 'origin', 'user_id', 'organization',
        'job_title', 'bio', 'photo_path', 'website_url', 'sort_order',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(EventSession::class, 'event_session_speaker', 'speaker_id', 'session_id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS['speaker'];
    }

    public function originLabel(): string
    {
        return self::ORIGINS[$this->origin] ?? self::ORIGINS['external'];
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/'.ltrim($this->photo_path, '/')) : null;
    }
}
