<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreOrderItem extends Model
{
    protected $fillable = ['order_id', 'parent_id', 'kind', 'variant_id', 'pack_id', 'name', 'variant_label', 'sku', 'unit_price', 'quantity', 'line_total', 'returned_quantity'];

    protected $casts = ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'quantity' => 'integer', 'returned_quantity' => 'integer'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreVariant::class, 'variant_id');
    }

    /** Les lignes « pack_item » ne se vendent ni ne se retournent seules : elles suivent leur pack. */
    public function isChild(): bool
    {
        return $this->kind === 'pack_item';
    }
}
