<?php

namespace App\Models;

use App\Support\OrgScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Défi thématique lancé par un bureau de chapitre ou de projet : les clubs invités y proposent des activités,
 * qu'un comité note selon le barème entre la fin du défi et la publication des résultats.
 */
class Challenge extends Model
{
    public const STATUSES = ['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'];

    public const PHASES = [
        'draft' => 'Brouillon',
        'upcoming' => 'À venir',
        'running' => 'En cours',
        'closed' => 'Terminé — en attente d’évaluation',
        'evaluation' => 'En évaluation',
        'results' => 'Résultats publiés',
    ];

    public const KINDS = ['theme' => 'Dans la thématique', 'off_theme' => 'Hors thématique'];

    protected $fillable = ['title', 'scope', 'invited_scopes', 'theme', 'description', 'starts_at', 'ends_at', 'evaluation_at', 'results_at', 'status', 'created_by'];

    protected $casts = [
        'invited_scopes' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'evaluation_at' => 'datetime',
        'results_at' => 'datetime',
    ];

    public function criteria(): HasMany
    {
        return $this->hasMany(ChallengeCriterion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClubActivity::class);
    }

    public function committee(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'challenge_committee')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Étape du défi selon le calendrier (brouillon et archivé mis à part). */
    public function phase(): string
    {
        if (! $this->isPublished()) {
            return 'draft';
        }

        $now = now();

        return match (true) {
            $now->lt($this->starts_at) => 'upcoming',
            $now->lte($this->ends_at) => 'running',
            $now->lt($this->evaluation_at) => 'closed',
            $now->lt($this->results_at) => 'evaluation',
            default => 'results',
        };
    }

    public function phaseLabel(): string
    {
        return self::PHASES[$this->phase()];
    }

    /** Les clubs peuvent proposer des activités tant que le défi est publié et pas terminé. */
    public function acceptsActivities(): bool
    {
        return $this->isPublished() && now()->lte($this->ends_at);
    }

    /** Le comité note à partir de la fin du défi, jusqu'à la publication des résultats. */
    public function scoringOpen(): bool
    {
        return $this->isPublished() && now()->gte($this->ends_at) && now()->lt($this->results_at);
    }

    public function resultsVisible(): bool
    {
        return $this->isPublished() && now()->gte($this->results_at);
    }

    public function isCommitteeMember(?User $user): bool
    {
        return $user !== null && $this->committee()->whereKey($user->id)->exists();
    }

    /** Périmètres dont les clubs sont invités (par défaut celui du défi). */
    public function invitedScopes(): array
    {
        $scopes = array_values(array_filter((array) $this->invited_scopes));

        return $scopes !== [] ? $scopes : array_filter([$this->scope]);
    }

    /** Le défi concerne l'un des périmètres donnés (organisateur ou invités) ; null = tous les périmètres. */
    public function reachesScopes(?array $scopes): bool
    {
        if ($scopes === null) {
            return true;
        }

        $targets = array_values(array_unique(array_filter(array_merge($this->invitedScopes(), [$this->scope]))));

        foreach ($scopes as $scope) {
            foreach ($targets as $target) {
                if (OrgScopes::covers((string) $target, (string) $scope)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Un club est invité si son périmètre est couvert par l'un des périmètres invités (aucun périmètre : tous les clubs). */
    public function invitesClub(OrgNode $club): bool
    {
        $invited = $this->invitedScopes();

        if ($invited === []) {
            return true;
        }

        foreach ($invited as $scope) {
            if ($club->scope !== null && OrgScopes::covers((string) $scope, (string) $club->scope)) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, ChallengeCriterion> */
    public function criteriaOf(string $kind): Collection
    {
        return $this->relationLoaded('criteria')
            ? $this->criteria->where('kind', $kind)->values()
            : $this->criteria()->where('kind', $kind)->get();
    }
}
