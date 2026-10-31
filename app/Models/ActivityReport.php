<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Compte rendu de clôture d'une activité : brouillon, soumis au président du club, puis validé (transmis au comité).
 */
class ActivityReport extends Model
{
    public const STATUSES = [
        'draft' => 'Brouillon',
        'pending' => 'En attente de validation du président',
        'validated' => 'Validé et transmis au comité',
    ];

    public const IMPLICATION = [1 => 'Faible', 2 => 'Moyenne', 3 => 'Bonne', 4 => 'Très bonne', 5 => 'Exceptionnelle'];

    public const NATURES = [
        'sponsor' => 'Sponsoring financier',
        'in_kind' => 'Don ou prestation en nature',
        'logistics' => 'Logistique (lieu, matériel, transport)',
        'media' => 'Partenaire média',
        'institutional' => 'Soutien institutionnel',
        'other' => 'Autre',
    ];

    /** Retombées médias : un lien est exigé pour Facebook et Instagram, facultatif pour la télévision, la radio et la presse. */
    public const MEDIA_KINDS = [
        'facebook' => 'Publication Facebook',
        'instagram' => 'Publication Instagram',
        'tv' => 'Passage TV',
        'radio' => 'Passage radio',
        'magazine' => 'Article de magazine',
    ];

    /** Notes de 1 à 5 de l'avis d'un participant. */
    public const RATINGS = [1 => 'Mauvais', 2 => 'Passable', 3 => 'Bien', 4 => 'Très bien', 5 => 'Excellent'];

    protected $fillable = [
        'activity_id', 'status', 'summary', 'hr_summary', 'self_scores', 'self_strengths', 'self_improvements', 'budget_planned', 'budget_spent', 'budget_income', 'budget_notes',
        'president_comment', 'submitted_at', 'submitted_by', 'validated_at', 'validated_by',
    ];

    protected $casts = [
        'self_scores' => 'array',
        'budget_planned' => 'float',
        'budget_spent' => 'float',
        'budget_income' => 'float',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ActivityReportMember::class, 'report_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ActivityReportItem::class, 'report_id');
    }

    public function partners(): HasMany
    {
        return $this->hasMany(ActivityReportPartner::class, 'report_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Rédaction ouverte : tant que le président n'a pas été saisi (ou après un renvoi), le club modifie son compte rendu. */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function posters()
    {
        return $this->items->where('kind', 'poster')->values();
    }

    /** Preuve de l'activité : la publication Facebook de la page du club (aucune photo n'est hébergée). */
    public function proofs()
    {
        return $this->items->where('kind', 'proof')->values();
    }

    public function mediaLinks()
    {
        return $this->items->whereIn('kind', array_keys(self::MEDIA_KINDS))->values();
    }

    /**
     * Auto-évaluation du club selon le barème qui correspond à l'activité : ne compte pas dans la note du comité.
     *
     * @return array{rows: \Illuminate\Support\Collection<int, array{criterion: ChallengeCriterion, points: ?float, comment: ?string}>, total: ?float, max: int}
     */
    public function selfEvaluation(): array
    {
        $activity = $this->activity;
        $grid = $activity->challenge->criteriaOf($activity->in_theme ? 'theme' : 'off_theme');
        $saved = (array) $this->self_scores;

        $rows = $grid->map(fn (ChallengeCriterion $criterion): array => [
            'criterion' => $criterion,
            'points' => isset($saved[$criterion->id]['points']) && $saved[$criterion->id]['points'] !== '' ? (float) $saved[$criterion->id]['points'] : null,
            'comment' => filled($saved[$criterion->id]['comment'] ?? null) ? (string) $saved[$criterion->id]['comment'] : null,
        ])->values();

        return [
            'rows' => $rows,
            'total' => $rows->isNotEmpty() && $rows->every(fn (array $row): bool => $row['points'] !== null) ? round($rows->sum('points'), 2) : null,
            'max' => (int) $grid->sum('max_points'),
        ];
    }

    /**
     * Avis des participants recueillis au scan du QR code (anonymes dans le compte rendu).
     *
     * @return array{count: int, registered: int, present: int, overall: ?float, organization: ?float, content: ?float, comments: array<int, string>}
     */
    public function participantFeedback(): array
    {
        $activity = $this->activity;
        $answers = $activity->registrations()->whereNotNull('feedback_at')->orderBy('feedback_at')->get();
        $average = fn (string $column): ?float => $answers->isEmpty() ? null : round((float) $answers->avg($column), 2);

        return [
            'count' => $answers->count(),
            'registered' => $activity->registeredCount(),
            'present' => $activity->registrations()->whereNotNull('checked_in_at')->count(),
            'overall' => $average('feedback_overall'),
            'organization' => $average('feedback_organization'),
            'content' => $average('feedback_content'),
            'comments' => $answers->pluck('feedback_comment')->filter(fn ($comment): bool => filled($comment))->values()->all(),
        ];
    }

    /** Montant total des soutiens financiers déclarés. */
    public function sponsoredAmount(): float
    {
        return (float) $this->partners->sum(fn (ActivityReportPartner $partner): float => (float) $partner->amount);
    }

    public static function fileUrl(?string $path): ?string
    {
        return $path !== null && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }
}
