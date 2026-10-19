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
        'phone',
        'phone_secondary',
        'gender',
        'birth_date',
        'address',
        'governorate_id',
        'delegation',
        'cin',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
        'bio',
        'photo_path',
        'cover_path',
        'education',
        'experience',
        'skills',
        'languages',
        'privacy_settings',
    ];

    /** Le CIN ne doit jamais fuiter dans une reponse JSON. */
    protected $hidden = ['cin'];

    protected $casts = [
        'privacy_settings' => 'array',
        'birth_date' => 'date',
        'cin' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
