<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EventPayment extends Model
{
    public const METHODS = [
        'cash' => 'Cash (espèces)',
        'deposit' => 'Versement',
        'transfer' => 'Virement',
        'd17' => 'D17',
        'e_dinar' => 'E-Dinar',
    ];

    public const STATUSES = [
        'pending' => 'En attente',
        'validated' => 'Validé',
        'rejected' => 'Rejeté',
    ];

    /** Disque privé des preuves de paiement. */
    public const PROOF_DISK = 'local';

    protected $fillable = [
        'event_id', 'registration_id', 'method', 'amount', 'reference', 'proof_path',
        'status', 'submitted_by', 'validated_by', 'validated_at', 'rejection_reason', 'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'validated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function hasProof(): bool
    {
        return $this->proof_path && Storage::disk(self::PROOF_DISK)->exists($this->proof_path);
    }
}
