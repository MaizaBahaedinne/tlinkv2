<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Niveau défini par l'organisateur (Platinum, Gold, Partenaire média…). `rank` 1 = le plus haut. */
class EventSponsorLevel extends Model
{
    public const KINDS = [
        'sponsor' => 'Sponsor',
        'partner' => 'Partenaire',
    ];

    protected $fillable = ['event_id', 'name', 'kind', 'rank'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(EventSponsor::class, 'level_id')->orderBy('sort_order')->orderBy('name');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS['sponsor'];
    }
}
