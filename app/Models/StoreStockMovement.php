<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreStockMovement extends Model
{
    public const REASONS = [
        'initial' => 'Stock initial',
        'restock' => 'Réapprovisionnement',
        'adjustment' => 'Correction',
        'sale' => 'Vente',
        'cancel' => 'Annulation',
        'return' => 'Retour client',
    ];

    protected $fillable = ['variant_id', 'delta', 'balance', 'reason', 'order_id', 'return_id', 'user_id', 'note'];

    protected $casts = ['delta' => 'integer', 'balance' => 'integer'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
