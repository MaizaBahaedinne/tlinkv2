<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Partenaire de la Fondation, affiché (logo + lien) sur le site vitrine.
 */
class Partner extends Model
{
    protected $fillable = ['name', 'logo_path', 'website_url', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function logoUrl(): string
    {
        return Storage::disk('public')->url($this->logo_path);
    }

    /** Lien uniquement en http(s) (évite les schémas dangereux type javascript:). */
    public function safeUrl(): ?string
    {
        return $this->website_url && preg_match('#^https?://#i', $this->website_url) === 1 ? $this->website_url : null;
    }
}
