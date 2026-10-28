<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Entreprise citée dans les expériences professionnelles des membres.
 */
class Company extends Model
{
    protected $fillable = ['name', 'slug', 'logo_path', 'website_url', 'created_by'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** Lien uniquement en http(s) (évite les schémas dangereux type javascript:). */
    public function safeUrl(): ?string
    {
        return $this->website_url && preg_match('#^https?://#i', $this->website_url) === 1 ? $this->website_url : null;
    }
}
