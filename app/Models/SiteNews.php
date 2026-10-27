<?php

namespace App\Models;

use App\Support\OrgScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Actualité du site vitrine : texte traduit (fr/en/ar), couverture, périmètre optionnel.
 * Système indépendant des publications du fil T.Link (table `posts`).
 */
class SiteNews extends Model
{
    public const STATUSES = ['draft' => 'Brouillon', 'published' => 'Publiée'];

    protected $table = 'site_news';

    protected $fillable = ['title', 'excerpt', 'body', 'cover_path', 'scope', 'author_id', 'feed_post_id', 'status', 'published_at'];

    protected $casts = [
        'title' => 'array',
        'excerpt' => 'array',
        'body' => 'array',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Publication du fil T.Link qui relaie l'article (facultative). */
    public function feedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'feed_post_id');
    }

    /** Actualités de la même catégorie : le périmètre, une actualité sans périmètre relevant de la Fondation. */
    public function scopeInCategory(Builder $query, ?string $scope): Builder
    {
        return $scope === null || $scope === 'foundation'
            ? $query->where(fn (Builder $inner) => $inner->whereNull('scope')->orWhere('scope', 'foundation'))
            : $query->where('scope', $scope);
    }

    public function publicUrl(string $locale = 'fr'): string
    {
        return route('site.news.show', ['locale' => $locale, 'news' => $this->id]);
    }

    /** Nom de la catégorie (le périmètre) dans la langue courante ; sans périmètre : la Fondation. */
    public function categoryLabel(): string
    {
        $slug = $this->scope ?: 'foundation';
        $key = 'site.scopes.'.$slug.'.name';
        $translated = __($key);

        return $translated !== $key ? $translated : (string) (OrgScopes::label($slug) ?? '');
    }

    /** Actualités visibles sur le site : publiées et dont la date est atteinte (une date future programme la parution). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function isVisible(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
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

    /** Résumé affiché sur les cartes : celui saisi, sinon le début du texte. */
    public function summary(?string $locale = null): string
    {
        $excerpt = $this->text('excerpt', $locale);

        return $excerpt !== '' ? $excerpt : \Illuminate\Support\Str::limit(strip_tags($this->text('body', $locale)), 160);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
