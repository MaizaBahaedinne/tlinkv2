<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Version de l'application : notes de version rédigées par l'administration globale (journal des changements).
 */
class AppRelease extends Model
{
    /** Catégories de changements, dans l'ordre d'affichage. */
    public const TYPES = [
        'new' => 'Nouveautés',
        'improvement' => 'Améliorations',
        'fix' => 'Corrections',
        'security' => 'Sécurité',
    ];

    public const STATUSES = ['draft' => 'Brouillon', 'published' => 'Publiée'];

    protected $fillable = ['version', 'title', 'summary', 'notes', 'status', 'published_at', 'author_id'];

    protected $casts = [
        'notes' => 'array',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return array<string, array<int, string>> catégorie => lignes (catégories vides omises) */
    public function groupedChanges(): array
    {
        $grouped = [];

        foreach (array_keys(self::TYPES) as $type) {
            $lines = array_values(array_filter((array) ($this->notes[$type] ?? []), fn ($line): bool => is_string($line) && trim($line) !== ''));

            if ($lines !== []) {
                $grouped[$type] = $lines;
            }
        }

        return $grouped;
    }

    public function changeCount(): int
    {
        return array_sum(array_map('count', $this->groupedChanges()));
    }
}
