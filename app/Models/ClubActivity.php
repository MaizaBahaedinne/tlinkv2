<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** Activité proposée par un club (éventuellement co-organisée) dans le cadre d'un défi ; notée par le comité du défi. */
class ClubActivity extends Model
{
    public const TYPES = [
        'conference' => 'Conférence',
        'training' => 'Formation / Workshop',
        'event' => 'Événement général',
        'teambuilding' => 'Team building',
        'camping' => 'Camping / Retreat',
        'competition' => 'Compétition / Challenge',
    ];

    public const AUDIENCES = [
        'department' => 'Département du club',
        'club' => 'Interne au club',
        'tunimateurs' => 'Tunimateurs uniquement',
        'public' => 'Public',
    ];

    public const STATUSES = ['draft' => 'Brouillon', 'published' => 'Publiée', 'cancelled' => 'Annulée'];

    public const FIELD_TYPES = ['text' => 'Texte court', 'textarea' => 'Texte long', 'choice' => 'Choix dans une liste', 'checkbox' => 'Case à cocher'];

    public const MAX_FIELDS = 10;

    protected $fillable = [
        'challenge_id', 'club_id', 'title', 'description', 'cover_path', 'type', 'audience', 'department_id', 'in_theme',
        'starts_at', 'ends_at', 'location', 'capacity', 'registration_ends_at', 'registration_fields', 'status', 'created_by',
    ];

    protected $casts = [
        'in_theme' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_ends_at' => 'datetime',
        'registration_fields' => 'array',
    ];

    protected static function booted(): void
    {
        // Jeton du QR code de présence : non devinable, hors $fillable.
        static::creating(function (self $activity): void {
            $activity->checkin_token = Str::random(40);
        });
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'club_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'department_id');
    }

    public function organizers(): HasMany
    {
        return $this->hasMany(ActivityOrganizer::class, 'activity_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ActivityRegistration::class, 'activity_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ActivityScore::class, 'activity_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(ActivityReport::class, 'activity_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ActivityTask::class, 'activity_id')->orderBy('sort_order')->orderBy('id');
    }

    /** Activité libre : le club n'a pas choisi de défi (ni thématique, ni barème, ni comité). */
    public function hasChallenge(): bool
    {
        return $this->challenge_id !== null;
    }

    /**
     * Tâches groupées par équipe (événement, marketing, RH, sponsoring), dans l'ordre des équipes ; les équipes vides sont omises.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, ActivityTask>>
     */
    public function tasksByTeam(): \Illuminate\Support\Collection
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->with('assignee:id,name')->get();
        $grouped = $tasks->groupBy('team');

        return collect(ActivityTask::TEAMS)->map(fn (string $label, string $team) => $grouped->get($team, collect()))->filter(fn ($list) => $list->isNotEmpty());
    }

    /** L'activité est terminée : le club peut rédiger son compte rendu de clôture. */
    public function isFinished(): bool
    {
        return $this->status === 'published' && $this->endsAt()->isPast();
    }

    /** Image de couverture (bandeau de la fiche) ; null sans image. */
    public function coverUrl(): ?string
    {
        return $this->cover_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->cover_path) : null;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES['event'];
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    /** Fin de l'activité (à défaut de date de fin : 4 h après le début). */
    public function endsAt(): \Illuminate\Support\Carbon
    {
        return $this->ends_at ?? $this->starts_at->copy()->addHours(4);
    }

    /** Identifiants des clubs organisateurs : le club porteur et les co-organisateurs ayant accepté. */
    public function organizerClubIds(): array
    {
        $accepted = $this->relationLoaded('organizers')
            ? $this->organizers->where('status', 'accepted')->pluck('club_id')->all()
            : $this->organizers()->where('status', 'accepted')->pluck('club_id')->all();

        return array_values(array_unique(array_map('intval', [$this->club_id, ...$accepted])));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && ($this->challenge_id === null || $this->challenge?->isPublished() === true);
    }

    public function registeredCount(): int
    {
        return $this->registrations()->where('status', 'registered')->count();
    }

    public function registrationOpen(): bool
    {
        return $this->isPublished() && now()->lte($this->registration_ends_at ?? $this->endsAt());
    }

    /** Le QR code ne valide la présence que de 1 h avant le début à 3 h après la fin. */
    public function checkinOpen(): bool
    {
        return $this->status === 'published'
            && now()->gte($this->starts_at->copy()->subHour())
            && now()->lte($this->endsAt()->copy()->addHours(3));
    }

    /** Adresse encodée dans le QR code de présence. */
    public function checkinUrl(): string
    {
        return route('activities.checkin', [$this, $this->checkin_token]);
    }

    /** @return array<int, array{label: string, type: string, required: bool, options: array<int, string>}> */
    public function fields(): array
    {
        return array_values(array_filter((array) $this->registration_fields, fn ($field): bool => is_array($field) && filled($field['label'] ?? null)));
    }
}
