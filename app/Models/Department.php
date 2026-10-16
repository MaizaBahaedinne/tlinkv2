<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['entity_id', 'name', 'slug', 'description'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }
}
