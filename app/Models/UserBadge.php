<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBadge extends Model
{
    protected $fillable = ['user_id', 'badge_id', 'awarded_at'];

    protected $casts = [
        'awarded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    /**
     * Badges actifs obtenus par un membre, du plus récent au plus ancien.
     *
     * @return \Illuminate\Support\Collection<int, UserBadge>
     */
    public static function earnedBy(int $userId): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('user_id', $userId)
            ->whereHas('badge', fn ($query) => $query->where('is_active', true))
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->orderByDesc('id')
            ->get();
    }
}
