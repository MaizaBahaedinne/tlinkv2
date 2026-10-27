<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Projet de la Fondation présenté sur le site vitrine : texte traduit (fr/en/ar) et chiffres clés.
 */
class SiteProject extends Model
{
    public const STATUSES = ['ongoing' => 'En cours', 'completed' => 'Terminé', 'upcoming' => 'À venir'];

    /** Nombre maximal de chiffres clés par projet. */
    public const MAX_FIGURES = 8;

    protected $fillable = ['slug', 'name', 'summary', 'body', 'stats', 'cover_path', 'status', 'started_year', 'website_url', 'sort_order', 'is_published'];

    protected $casts = [
        'name' => 'array',
        'summary' => 'array',
        'body' => 'array',
        'stats' => 'array',
        'started_year' => 'integer',
        'sort_order' => 'integer',
        'is_published' => 'boolean',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Texte d'un champ traduisible : langue demandée, sinon français. */
    public function text(string $field, ?string $locale = null): string
    {
        $values = (array) ($this->{$field} ?? []);
        $locale ??= app()->getLocale();

        foreach ([$locale, 'fr'] as $code) {
            $value = trim((string) ($values[$code] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Chiffres clés dans la langue demandée, sans les lignes incomplètes.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function figures(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $figures = [];

        foreach ((array) $this->stats as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            $labels = (array) ($row['label'] ?? []);
            $label = trim((string) ($labels[$locale] ?? '')) ?: trim((string) ($labels['fr'] ?? ''));

            if ($value !== '' && $label !== '') {
                $figures[] = ['value' => $value, 'label' => $label];
            }
        }

        return $figures;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /** Lien uniquement en http(s) (évite les schémas dangereux type javascript:). */
    public function safeUrl(): ?string
    {
        return $this->website_url && preg_match('#^https?://#i', $this->website_url) === 1 ? $this->website_url : null;
    }
}
