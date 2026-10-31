<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReturnItem extends Model
{
    protected $fillable = ['return_id', 'order_item_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(StoreReturn::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(StoreOrderItem::class, 'order_item_id');
    }
}
