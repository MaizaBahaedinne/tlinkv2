<?php

namespace App\Models;

use App\Support\Store\StoreSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class StoreProduct extends Model
{
    protected $fillable = ['category_id', 'type_id', 'name', 'slug', 'summary', 'description', 'image_path', 'price', 'is_active', 'is_featured'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean', 'is_featured' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(StoreType::class, 'type_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(StoreVariant::class, 'product_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** Stock vendable : variantes actives uniquement. */
    public function availableStock(): int
    {
        return (int) $this->variants->where('is_active', true)->sum('stock');
    }

    /** Prix le plus bas parmi les variantes actives (le prix de base à défaut). */
    public function lowestPrice(): float
    {
        $prices = $this->variants->where('is_active', true)->map(fn (StoreVariant $variant): float => $variant->unitPrice());

        return (float) ($prices->min() ?? $this->price);
    }

    public function hasVariedPrices(): bool
    {
        return $this->variants->where('is_active', true)->map(fn (StoreVariant $variant): float => $variant->unitPrice())->unique()->count() > 1;
    }

    public function priceLabel(): string
    {
        return ($this->hasVariedPrices() ? 'À partir de ' : '').StoreSettings::money($this->lowestPrice());
    }
}
