<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebPushSubscription extends Model
{
    protected $fillable = ['user_id', 'app', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'user_agent', 'last_seen_at'];

    protected $hidden = ['public_key', 'auth_token'];

    protected $casts = ['last_seen_at' => 'datetime'];

    /** Navigateurs de l'application donnée (T.Link, Store, Academy, Messenger) ; tous si elle n'a pas de sous-domaine propre. */
    public function scopeForSection(Builder $query, string $section): Builder
    {
        return \App\Support\Domains::host($section) === null ? $query : $query->where('app', $section);
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
