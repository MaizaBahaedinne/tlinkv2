<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StoreProductImage extends Model
{
    protected $fillable = ['product_id', 'color_id', 'path', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(StoreColor::class, 'color_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
