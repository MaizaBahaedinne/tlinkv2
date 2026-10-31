<?php

namespace App\Models;

use App\Support\Store\StoreSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreOrder extends Model
{
    /** Ordre du parcours : une commande n'avance que vers un statut plus loin (ou s'annule avant l'expédition). */
    public const STATUSES = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'preparing' => 'En préparation',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];

    public const CANCELLABLE = ['pending', 'confirmed', 'preparing'];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Non payée',
        'paid' => 'Payée',
        'partially_refunded' => 'Partiellement remboursée',
        'refunded' => 'Remboursée',
    ];

    protected $fillable = [
        'reference', 'user_id', 'status', 'payment_status', 'delivery_method_id', 'delivery_name', 'delivery_kind',
        'payment_method_id', 'payment_key', 'payment_name', 'ship_name', 'ship_phone', 'ship_governorate_id', 'ship_city',
        'ship_address', 'subtotal', 'delivery_fee', 'total', 'refunded_amount', 'note', 'admin_note', 'tracking_number',
        'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'delivery_fee' => 'decimal:2', 'total' => 'decimal:2', 'refunded_amount' => 'decimal:2',
        'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'ship_governorate_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class, 'order_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(StoreReturn::class, 'order_id');
    }

    public function isPickup(): bool
    {
        return $this->delivery_kind === 'pickup';
    }

    public function statusLabel(): string
    {
        if ($this->status === 'shipped' && $this->isPickup()) {
            return 'Prête au retrait';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    /** Ton du badge admin (success, warning, danger, info, neutral). */
    public function statusTone(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'confirmed', 'preparing', 'shipped' => 'info',
            'delivered' => 'success',
            default => 'neutral',
        };
    }

    public function paymentTone(): string
    {
        return match ($this->payment_status) {
            'unpaid' => 'warning',
            'paid' => 'success',
            'partially_refunded' => 'info',
            default => 'neutral',
        };
    }

    public function totalLabel(): string
    {
        return StoreSettings::money((float) $this->total);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, self::CANCELLABLE, true);
    }

    /** Statuts qu'on peut encore atteindre depuis le statut actuel (hors annulation). */
    public function nextStatuses(): array
    {
        $order = array_keys(array_diff_key(self::STATUSES, ['cancelled' => 1]));
        $position = array_search($this->status, $order, true);

        return $position === false ? [] : array_slice($order, $position + 1);
    }
}
