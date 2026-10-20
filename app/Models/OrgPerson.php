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
        $path = $this->user_id ? $this->user?->profile?->photo_path : $this->photo_path;

        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    /** Une personne liee a un compte est ce compte : nom, e-mail, telephone et photo sont lus a la source. */
    public function displayName(): string
    {
        return $this->user?->name ?? $this->full_name;
    }

    public function displayEmail(): ?string
    {
        if (! $this->user_id) {
            return $this->email;
        }

        $email = $this->user?->email;

        return $email === null || str_ends_with($email, '@legacy.invalid') ? null : $email;
    }

    public function displayPhone(): ?string
    {
        return $this->user_id ? $this->user?->profile?->phone : $this->phone;
    }
}
