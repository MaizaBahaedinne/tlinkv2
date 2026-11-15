<?php

namespace App\Models;

use App\Support\Challenges\ActivityTaskTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Membre du comité d'organisation d'une activité : coordinateur ou responsable d'une équipe (événement, marketing, RH, sponsoring). */
class ActivityCommitteeMember extends Model
{
    public const ROLES = ['lead' => 'Coordinateur(trice)'] + ActivityTaskTemplates::TEAMS + ['member' => 'Membre du comité'];

    protected $fillable = ['activity_id', 'user_id', 'role'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'lead', 'member' => self::ROLES[$this->role],
            default => 'Équipe '.mb_strtolower(self::ROLES[$this->role] ?? $this->role),
        };
    }
}
