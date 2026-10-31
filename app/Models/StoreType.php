<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Type d'article : T-shirt, sweat, casquette, mug… */
class StoreType extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function products(): HasMany
    {
        return $this->hasMany(StoreProduct::class, 'type_id');
    }
}
