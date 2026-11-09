<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDevice extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'app', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    /** Appareils de l'application donnée (T.Link, Store, Academy, Messenger) ; tous si elle n'a pas de sous-domaine propre. */
    public function scopeForSection(Builder $query, string $section): Builder
    {
        return \App\Support\Domains::host($section) === null ? $query : $query->where('app', $section);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
