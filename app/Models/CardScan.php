<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un pointage : entrée ou sortie d'un membre sur une activité, un événement, un local… */
class CardScan extends Model
{
    public const DIRECTIONS = ['in' => 'Entrée', 'out' => 'Sortie'];

    public const METHODS = ['qr' => 'QR code', 'nfc' => 'NFC', 'manual' => 'Saisie du numéro'];

    protected $fillable = [
        'member_card_id', 'user_id', 'subject_type', 'subject_id', 'subject_label', 'scope', 'node_id',
        'direction', 'method', 'scanned_by', 'scanned_at',
    ];

    protected $casts = ['scanned_at' => 'datetime'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(MemberCard::class, 'member_card_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
