<?php

namespace App\Models;

use App\Support\Store\StoreSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class StorePack extends Model
{
    protected $fillable = ['name', 'slug', 'summary', 'description', 'image_path', 'price', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(StorePackItem::class, 'pack_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function priceLabel(): string
    {
        return StoreSettings::money((float) $this->price);
    }

    /**
     * Nombre de packs que le stock permet encore de composer (chargez items.product.variants et items.variant).
     */
    public function availableUnits(): int
    {
        if ($this->items->isEmpty()) {
            return 0;
        }

        return (int) $this->items->map(function (StorePackItem $item): int {
            $stock = $item->variant !== null
                ? ($item->variant->is_active ? $item->variant->stock : 0)
                : (int) $item->product?->variants->where('is_active', true)->sum('stock');

            return intdiv($stock, max(1, $item->quantity));
        })->min();
    }
}
