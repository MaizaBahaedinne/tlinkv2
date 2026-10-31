<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePackItem extends Model
{
    protected $fillable = ['pack_id', 'product_id', 'variant_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(StorePack::class, 'pack_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreVariant::class, 'variant_id');
    }
}
