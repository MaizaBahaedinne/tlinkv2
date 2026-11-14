<?php

namespace App\Models;

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
        'club_id', 'platform', 'name', 'login', 'password', 'profile_url', 'recovery_email', 'recovery_phone', 'two_factor',
        'recovery_codes', 'notes', 'password_changed_at', 'created_by', 'updated_by',
    ];

    protected $hidden = ['password', 'recovery_codes'];

    protected $casts = [
        'password' => 'encrypted',
        'recovery_codes' => 'encrypted',
        'password_changed_at' => 'datetime',
    ];

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
