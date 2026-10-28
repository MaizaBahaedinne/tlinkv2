<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Badge extends Model
{
    /**
     * Catégories de badges selon la complexité des missions.
     * `gradient` colore la médaille, `chip` l'étiquette de catégorie.
     */
    public const TIERS = [
        'bronze' => ['label' => 'Bronze', 'gradient' => 'from-amber-700 to-orange-400', 'chip' => 'border-amber-200 bg-amber-50 text-amber-800'],
        'silver' => ['label' => 'Argent', 'gradient' => 'from-slate-500 to-slate-300', 'chip' => 'border-slate-300 bg-slate-100 text-slate-700'],
        'gold' => ['label' => 'Or', 'gradient' => 'from-yellow-500 to-amber-300', 'chip' => 'border-yellow-300 bg-yellow-50 text-yellow-800'],
        'platinum' => ['label' => 'Platine', 'gradient' => 'from-sky-500 to-violet-500', 'chip' => 'border-violet-200 bg-violet-50 text-violet-800'],
    ];

    protected $fillable = ['slug', 'name', 'description', 'tier', 'icon', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class)->orderBy('sort_order')->orderBy('id');
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function tierLabel(): string
    {
        return self::TIERS[$this->tier]['label'] ?? ucfirst($this->tier);
    }

    public function gradient(): string
    {
        return self::TIERS[$this->tier]['gradient'] ?? self::TIERS['bronze']['gradient'];
    }

    public function chip(): string
    {
        return self::TIERS[$this->tier]['chip'] ?? self::TIERS['bronze']['chip'];
    }

    /** Classe FontAwesome sûre : on n'accepte que des mots simples. */
    public function iconClass(): string
    {
        return preg_match('/^[a-z0-9 \-]+$/', (string) $this->icon) === 1 ? $this->icon : 'fa-solid fa-medal';
    }
}
