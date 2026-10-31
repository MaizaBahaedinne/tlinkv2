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

    /** Retombées médias : le lien est facultatif pour la télévision, la radio et la presse. */
    public const MEDIA_KINDS = [
        'facebook' => 'Publication Facebook',
        'instagram' => 'Publication Instagram',
        'tv' => 'Passage TV',
        'radio' => 'Passage radio',
        'magazine' => 'Article de magazine',
    ];

    public const MAX_PROOFS = 10;

    protected $fillable = [
        'activity_id', 'status', 'summary', 'hr_summary', 'budget_planned', 'budget_spent', 'budget_income', 'budget_notes',
        'president_comment', 'submitted_at', 'submitted_by', 'validated_at', 'validated_by',
    ];

    protected $casts = [
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

    public function proofs()
    {
        return $this->items->where('kind', 'proof')->values();
    }

    public function mediaLinks()
    {
        return $this->items->whereIn('kind', array_keys(self::MEDIA_KINDS))->values();
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
