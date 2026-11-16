<?php

namespace App\Models;

use App\Support\MyOffice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Guide d'utilisation : une suite d'étapes illustrées qui apprend à se servir d'une fonction de T.Link.
 * Rédigé et publié par l'administration ; consultable par les membres dans la bibliothèque des guides.
 */
class Guide extends Model
{
    public const STATUSES = ['draft' => 'Brouillon', 'published' => 'Publié'];

    public const AUDIENCES = ['all' => 'Tous les membres', 'holders' => 'Membres qui occupent un poste'];

    /** Couleur du guide : classes complètes (lues telles quelles par Tailwind). */
    public const ACCENTS = [
        'rose' => ['label' => 'Rouge T.Link', 'soft' => 'bg-rose-50 text-rose-600', 'strong' => 'bg-rose-600 text-white', 'bar' => 'bg-rose-500'],
        'sky' => ['label' => 'Bleu', 'soft' => 'bg-sky-50 text-sky-600', 'strong' => 'bg-sky-600 text-white', 'bar' => 'bg-sky-500'],
        'emerald' => ['label' => 'Vert', 'soft' => 'bg-emerald-50 text-emerald-600', 'strong' => 'bg-emerald-600 text-white', 'bar' => 'bg-emerald-500'],
        'amber' => ['label' => 'Orange', 'soft' => 'bg-amber-50 text-amber-600', 'strong' => 'bg-amber-600 text-white', 'bar' => 'bg-amber-500'],
        'violet' => ['label' => 'Violet', 'soft' => 'bg-violet-50 text-violet-600', 'strong' => 'bg-violet-600 text-white', 'bar' => 'bg-violet-500'],
    ];

    /** Icônes Font Awesome proposées (sans le préfixe « fa-solid fa- »). */
    public const ICONS = [
        'book-open' => 'Livre', 'compass' => 'Boussole', 'house' => 'Maison', 'user' => 'Profil', 'id-card' => 'Carte',
        'qrcode' => 'QR code', 'briefcase' => 'Bureau', 'share-nodes' => 'Partage', 'envelope' => 'E-mail', 'comments' => 'Discussion',
        'bell' => 'Notification', 'magnifying-glass' => 'Recherche', 'pen' => 'Écrire', 'image' => 'Image', 'calendar' => 'Calendrier',
        'trophy' => 'Trophée', 'graduation-cap' => 'Formation', 'bag-shopping' => 'Boutique', 'gear' => 'Réglages', 'shield-halved' => 'Sécurité',
        'users' => 'Équipe', 'clipboard-check' => 'Tâches', 'life-ring' => 'Aide', 'paper-plane' => 'Envoyer', 'hand-pointer' => 'Cliquer',
        'lightbulb' => 'Idée', 'circle-check' => 'Validé', 'star' => 'Étoile', 'people-group' => 'Adhésions', 'sitemap' => 'Organigramme',
    ];

    protected $fillable = ['slug', 'title', 'summary', 'category', 'audience', 'icon', 'accent', 'status', 'sort_order', 'published_at', 'created_by', 'updated_by'];

    protected $casts = [
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(GuideStep::class)->orderBy('position')->orderBy('id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(GuideProgress::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Guides qu'un membre peut ouvrir : publiés, et ceux réservés aux titulaires de poste seulement pour eux. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->published();

        if (! MyOffice::holdsPosition($user)) {
            $query->where('audience', 'all');
        }

        return $query;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function accent(): array
    {
        return self::ACCENTS[$this->accent] ?? self::ACCENTS['rose'];
    }

    public function iconClass(): string
    {
        return 'fa-solid fa-'.(array_key_exists($this->icon, self::ICONS) ? $this->icon : 'book-open');
    }

    /** Durée de lecture automatique, arrondie à la minute supérieure. */
    public function minutes(): int
    {
        $seconds = (int) ($this->steps_sum_duration ?? ($this->relationLoaded('steps') ? $this->steps->sum('duration') : $this->steps()->sum('duration')));

        return max(1, (int) ceil($seconds / 60));
    }

    /** Adresse lisible et unique à partir d'un titre. */
    public static function makeSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($title), 100, '') ?: 'guide';
        $slug = $base;

        for ($i = 2; static::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
