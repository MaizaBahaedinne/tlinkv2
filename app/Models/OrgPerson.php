<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgPerson extends Model
{
    protected $table = 'org_people';

    protected $fillable = [
        'user_id',
        'full_name',
        'job_title',
        'phone',
        'email',
        'photo_path',
        'contact_visible',
        'notes',
    ];

    protected $casts = [
        'contact_visible' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrgAssignment::class, 'person_id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? '/storage/'.ltrim($this->photo_path, '/') : null;
    }
}
