<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreVariant extends Model
{
    protected $attributes = ['is_active' => true, 'stock' => 0, 'low_stock_threshold' => 3];

    protected $fillable = ['product_id', 'color_id', 'size_id', 'sku', 'price', 'stock', 'low_stock_threshold', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'stock' => 'integer', 'low_stock_threshold' => 'integer', 'is_active' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(StoreColor::class, 'color_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(StoreSize::class, 'size_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StoreStockMovement::class, 'variant_id');
    }

    /** Prix de vente : celui de la variante, sinon celui de l'article. */
    public function unitPrice(): float
    {
        return (float) ($this->price ?? $this->product?->price ?? 0);
    }

    /** « Rouge / M », ou « Unique » pour un article sans couleur ni taille. */
    public function label(): string
    {
        $parts = array_filter([$this->color?->name, $this->size?->name]);

        return $parts === [] ? 'Unique' : implode(' / ', $parts);
    }

    public function isLow(): bool
    {
        return $this->stock <= $this->low_stock_threshold;
    }
}
