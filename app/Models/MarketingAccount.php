<?php

namespace App\Models;

use App\Support\EntityPages;
use App\Support\MarketingEntities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Accès partagé d'un compte marketing (Facebook, Instagram, e-mail). Mot de passe et codes de secours sont chiffrés
 * en base et ne sont jamais sérialisés : ils ne sortent que par la route « révéler », qui laisse une trace d'audit.
 */
class MarketingAccount extends Model
{
    public const PLATFORMS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'email' => 'E-mail'];

    /** Plateformes dont chaque club doit déposer l'accès. */
    public const REQUIRED = ['facebook', 'instagram'];

    public const TWO_FACTOR = ['none' => 'Non activée', 'app' => 'Application d’authentification', 'sms' => 'SMS', 'email' => 'E-mail'];

    protected $fillable = [
        'club_id', 'scope', 'platform', 'name', 'login', 'password', 'profile_url', 'recovery_email', 'recovery_phone', 'two_factor',
        'recovery_codes', 'notes', 'password_changed_at', 'created_by', 'updated_by',
    ];

    protected $hidden = ['password', 'recovery_codes'];

    protected $casts = [
        'password' => 'encrypted',
        'recovery_codes' => 'encrypted',
        'password_changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // La page saisie dans le coffre est recopiée sur la fiche de l'entité (club ou Fondation).
        static::saved(fn (self $account) => EntityPages::push($account));
        static::deleted(fn (self $account) => EntityPages::push($account));
    }

    public function entityKey(): string
    {
        return MarketingEntities::keyOf($this->club_id, $this->scope);
    }

    public function entityLabel(): string
    {
        return MarketingEntities::label($this->club_id, $this->scope);
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'club_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function platformLabel(): string
    {
        return self::PLATFORMS[$this->platform] ?? ucfirst($this->platform);
    }
}
