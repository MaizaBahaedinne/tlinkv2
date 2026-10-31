<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorePaymentMethod extends Model
{
    public const APPLIES_TO = ['all' => 'Toutes les livraisons', 'home' => 'Livraison à domicile', 'pickup' => 'Retrait sur place'];

    protected $fillable = ['key', 'name', 'instructions', 'applies_to', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function allowsDelivery(string $kind): bool
    {
        return $this->applies_to === 'all' || $this->applies_to === $kind;
    }
}
