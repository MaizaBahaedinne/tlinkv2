<?php

namespace App\Models;

use App\Support\Challenges\ActivityTaskTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Tâche d'une activité de club : reprise d'une liste type ou ajoutée, affectée à un membre et suivie jusqu'à son terme. */
class ActivityTask extends Model
{
    public const TEAMS = ActivityTaskTemplates::TEAMS;

    public const STATUSES = ['todo' => 'À faire', 'doing' => 'En cours', 'done' => 'Terminée'];

    protected $fillable = [
        'activity_id', 'team', 'label', 'notes', 'due_at', 'status', 'from_template', 'sort_order', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'due_at' => 'date',
        'from_template' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    /** Membres à qui la tâche est confiée (un ou plusieurs). */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_task_assignees', 'task_id', 'user_id')->withTimestamps()->orderBy('users.name');
    }

    public function teamLabel(): string
    {
        return self::TEAMS[$this->team] ?? $this->team;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    /** Échéance dépassée sur une tâche non terminée. */
    public function isLate(): bool
    {
        return ! $this->isDone() && $this->due_at !== null && $this->due_at->isBefore(now()->startOfDay());
    }
}
