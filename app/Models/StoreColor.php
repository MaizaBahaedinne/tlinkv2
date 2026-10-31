<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreColor extends Model
{
    protected $fillable = ['name', 'hex', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];
}
