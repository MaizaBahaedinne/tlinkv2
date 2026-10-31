<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de vérification d'identité (CIN tunisienne recto/verso + selfie), examinée à la main par un administrateur.
 */
class IdentityVerification extends Model
{
    public const STATUSES = ['pending' => 'En attente', 'approved' => 'Validée', 'rejected' => 'Refusée'];

    /** Clé de document => colonne. */
    public const DOCUMENTS = ['front' => 'id_front_path', 'back' => 'id_back_path', 'selfie' => 'selfie_path'];

    protected $fillable = ['user_id', 'status', 'id_front_path', 'id_back_path', 'selfie_path', 'rejection_reason', 'reviewed_by', 'reviewed_at'];

    protected $hidden = ['id_front_path', 'id_back_path', 'selfie_path'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
