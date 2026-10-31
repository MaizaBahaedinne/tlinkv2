<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreReturn extends Model
{
    public const STATUSES = [
        'requested' => 'Demandé',
        'approved' => 'Accepté',
        'rejected' => 'Refusé',
        'received' => 'Reçu',
        'completed' => 'Clôturé',
    ];

    public const RESOLUTIONS = ['refund' => 'Remboursement', 'exchange' => 'Échange'];

    protected $fillable = ['reference', 'order_id', 'user_id', 'status', 'reason', 'details', 'resolution', 'refund_amount', 'restocked', 'admin_note', 'decided_at', 'received_at', 'completed_at'];

    protected $casts = ['refund_amount' => 'decimal:2', 'restocked' => 'boolean', 'decided_at' => 'datetime', 'received_at' => 'datetime', 'completed_at' => 'datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreReturnItem::class, 'return_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function resolutionLabel(): string
    {
        return self::RESOLUTIONS[$this->resolution] ?? $this->resolution;
    }
}
