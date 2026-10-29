<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Abonnement Academy : accès aux cours « inclus dans les abonnements » pendant `duration_days`. */
class AcademyPlan extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'price', 'duration_days', 'is_active', 'sort_order'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean', 'duration_days' => 'integer'];

    public function priceLabel(): string
    {
        return AcademyCourse::money((float) $this->price).' / '.$this->durationLabel();
    }

    public function durationLabel(): string
    {
        return match (true) {
            $this->duration_days === 30 => 'mois',
            $this->duration_days === 365 => 'an',
            default => $this->duration_days.' jours',
        };
    }
}
