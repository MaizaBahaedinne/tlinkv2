<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreDeliveryMethod extends Model
{
    public const KINDS = ['home' => 'Livraison à domicile', 'pickup' => 'Retrait sur place'];

    protected $fillable = ['name', 'kind', 'fee', 'free_above', 'governorate_fees', 'estimated_delay', 'instructions', 'is_active', 'sort_order'];

    protected $casts = ['fee' => 'decimal:2', 'free_above' => 'decimal:2', 'governorate_fees' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    public function isPickup(): bool
    {
        return $this->kind === 'pickup';
    }

    /** Frais pour un sous-total et un gouvernorat : gratuit au-delà du seuil, sinon tarif du gouvernorat, sinon tarif de base. */
    public function feeFor(float $subtotal, ?int $governorateId): float
    {
        if ($this->isPickup()) {
            return 0.0;
        }

        if ($this->free_above !== null && $subtotal >= (float) $this->free_above) {
            return 0.0;
        }

        $perGovernorate = $this->governorate_fees[(string) $governorateId] ?? null;

        return (float) ($perGovernorate !== null && $perGovernorate !== '' ? $perGovernorate : $this->fee);
    }
}
