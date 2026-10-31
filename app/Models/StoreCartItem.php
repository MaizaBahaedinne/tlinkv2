<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCartItem extends Model
{
    protected $fillable = ['user_id', 'variant_id', 'pack_id', 'selection', 'quantity'];

    protected $casts = ['selection' => 'array', 'quantity' => 'integer'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreVariant::class, 'variant_id');
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(StorePack::class, 'pack_id');
    }
}
