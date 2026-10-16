<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active'];

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}
