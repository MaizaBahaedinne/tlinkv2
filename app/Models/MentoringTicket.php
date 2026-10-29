<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MentoringTicket extends Model
{
    public const TYPES = ['request' => 'Demande', 'incident' => 'Incident', 'issue' => 'Problème'];

    public const AUDIENCES = ['personal' => 'Personnel', 'collective' => 'Mon collectif'];

    public const COLLECTIVES = ['club' => 'Club', 'project' => 'Projet', 'chapter' => 'Chapter', 'other' => 'Autre collectif'];

    public const CATEGORIES = [
        'governance' => 'Gouvernance et organisation',
        'relations' => 'Relations et conflits',
        'project' => 'Projet et exécution',
        'resources' => 'Ressources et financement',
        'training' => 'Formation et compétences',
        'wellbeing' => 'Motivation et bien-être',
        'other' => 'Autre',
    ];

    public const PRIORITIES = ['low' => 'Basse', 'normal' => 'Normale', 'high' => 'Haute', 'urgent' => 'Urgente'];

    /** État => [libellé, ton du badge]. */
    public const STATUSES = [
        'new' => ['Reçue', 'info'],
        'qualified' => ['Qualifiée', 'violet'],
        'assigned' => ['Affectée', 'warning'],
        'in_progress' => ['En accompagnement', 'warning'],
        'resolved' => ['Résolue', 'success'],
        'closed' => ['Clôturée', 'neutral'],
        'rejected' => ['Non retenue', 'danger'],
    ];

    /** États où le ticket est encore à traiter. */
    public const OPEN = ['new', 'qualified', 'assigned', 'in_progress'];

    protected $fillable = [
        'reference', 'requester_id', 'type', 'audience', 'collective_type', 'collective_name', 'node_id', 'title', 'description',
        'status', 'category', 'priority', 'qualification_note', 'qualified_by', 'qualified_at',
        'mentor_id', 'assigned_by', 'assigned_at', 'started_at', 'resolved_at', 'closed_at',
    ];

    protected $casts = [
        'qualified_at' => 'datetime',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'node_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MentoringEvent::class, 'ticket_id')->orderBy('id');
    }

    public function rex(): HasOne
    {
        return $this->hasOne(MentoringRex::class, 'ticket_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUSES[$this->status][1] ?? 'neutral';
    }

    /** Collectif concerné (« Club Robotique Sfax », « Projet X »…), ou null pour un ticket personnel. */
    public function collectiveLabel(): ?string
    {
        if ($this->audience !== 'collective') {
            return null;
        }

        $name = $this->node?->title ?: $this->collective_name;
        $kind = self::COLLECTIVES[$this->collective_type] ?? null;

        return trim(($kind !== null && $this->collective_type !== 'other' ? $kind.' · ' : '').($name ?? '')) ?: ($kind ?? 'Collectif');
    }
}
