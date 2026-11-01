<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    public const DISK = 'local';

    public const LIFETIME_HOURS = 24;

    protected $fillable = ['user_id', 'kind', 'path', 'mime_type', 'caption', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /** Stories encore visibles (moins de 24 h). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }

    public function mediaUrl(): string
    {
        return route('stories.media', ['story' => $this->id]);
    }
}
