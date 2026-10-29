<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyOrder extends Model
{
    public const STATUSES = ['pending' => 'En attente', 'paid' => 'Payée', 'cancelled' => 'Annulée', 'refunded' => 'Remboursée'];

    protected $fillable = ['reference', 'user_id', 'course_id', 'plan_id', 'label', 'amount', 'currency', 'status', 'provider', 'provider_reference', 'paid_at', 'note'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'course_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AcademyPlan::class, 'plan_id');
    }

    public function amountLabel(): string
    {
        return AcademyCourse::money((float) $this->amount, $this->currency);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
