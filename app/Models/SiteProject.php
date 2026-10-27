<?php

namespace App\Models;

use App\Support\OrgScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

/**
 * Page « projet » du site vitrine : contenu (texte fr/en/ar, chiffres clés, couverture) d'un périmètre de type « Projet ».
 * Le nom, la description et le logo viennent du périmètre tant que la page ne les remplace pas.
 */
class SiteProject extends Model
{
    public const STATUSES = ['ongoing' => 'En cours', 'completed' => 'Terminé', 'upcoming' => 'À venir'];

    /** Nombre maximal de chiffres clés par projet. */
    public const MAX_FIGURES = 8;

    protected $fillable = ['slug', 'scope', 'name', 'summary', 'body', 'stats', 'cover_path', 'status', 'started_year', 'website_url', 'sort_order', 'is_published'];

    protected $casts = [
        'name' => 'array',
        'summary' => 'array',
        'body' => 'array',
        'stats' => 'array',
        'started_year' => 'integer',
        'sort_order' => 'integer',
        'is_published' => 'boolean',
    ];

    /** Page du périmètre donné : l'enregistrement s'il existe, sinon une page vide (non enregistrée). */
    public static function forScope(string $scope): static
    {
        return static::query()->where('scope', $scope)->first() ?? static::blank($scope);
    }

    public static function blank(string $scope): static
    {
        return new static(['scope' => $scope, 'slug' => $scope, 'status' => 'ongoing', 'is_published' => true, 'sort_order' => 0]);
    }

    /**
     * Pages de tous les périmètres de type Projet (publiées ou non), dans l'ordre d'affichage.
     *
     * @return Collection<int, static>
     */
    public static function allPages(): Collection
    {
        $stored = static::query()->whereNotNull('scope')->get()->keyBy('scope');

        return collect(OrgScopes::slugsOfType('project'))
            ->map(fn (string $scope): static => $stored->get($scope) ?? static::blank($scope))
            ->sort(fn (self $a, self $b): int => [$a->sort_order, $a->text('name')] <=> [$b->sort_order, $b->text('name')])
            ->values();
    }

    /**
     * Pages visibles sur le site : un périmètre de type Projet sans page enregistrée est affiché avec ses
     * informations de base ; une page enregistrée mais dépubliée est masquée.
     *
     * @return Collection<int, static>
     */
    public static function publicPages(): Collection
    {
        return static::allPages()->filter(fn (self $page): bool => $page->is_published)->values();
    }

    /** Texte d'un champ traduisible : langue demandée, sinon français, sinon valeur du périmètre. */
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

        return match ($field) {
            'name' => $this->scopeName(),
            'summary' => trim((string) (OrgScopes::descriptions()[$this->scope] ?? '')),
            default => '',
        };
    }

    private function scopeName(): string
    {
        $key = "site.scopes.{$this->scope}.name";

        return Lang::has($key) ? (string) __($key) : (string) OrgScopes::label($this->scope);
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

    /** Logo du périmètre (null s'il n'existe pas). */
    public function logoUrl(): ?string
    {
        $path = OrgScopes::logos()[$this->scope] ?? null;

        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }

    /** Lien uniquement en http(s) (évite les schémas dangereux type javascript:). */
    public function safeUrl(): ?string
    {
        return $this->website_url && preg_match('#^https?://#i', $this->website_url) === 1 ? $this->website_url : null;
    }
}
