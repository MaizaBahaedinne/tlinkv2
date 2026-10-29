<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentoringMentor extends Model
{
    protected $fillable = ['user_id', 'is_manager', 'is_active', 'specialties', 'created_by'];

    protected $casts = ['is_manager' => 'boolean', 'is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
