<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReview extends Model
{
    public const PUBLISHED = 'published';

    public const HIDDEN = 'hidden';

    protected $fillable = ['product_id', 'user_id', 'rating', 'title', 'body', 'status'];

    protected $casts = ['rating' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::PUBLISHED);
    }

    /** Seuls les membres dont une commande contenant l'article a été livrée peuvent le noter. */
    public static function canReview(?User $user, StoreProduct $product): bool
    {
        if (! $user) {
            return false;
        }

        return StoreOrderItem::query()
            ->whereHas('variant', fn ($query) => $query->where('product_id', $product->id))
            ->whereHas('order', fn ($query) => $query->where('user_id', $user->id)->where('status', 'delivered'))
            ->exists();
    }

    /** Prénom + initiale du nom : les avis n'exposent pas l'identité complète. */
    public function authorLabel(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->user?->name)) ?: [];
        $first = $parts[0] ?? 'Membre';

        return $first.(count($parts) > 1 ? ' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : '');
    }
}
