<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'headline',
        'bio',
        'photo_path',
        'cover_path',
        'education',
        'experience',
        'skills',
        'languages',
        'privacy_settings',
    ];

    protected $casts = [
        'privacy_settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
