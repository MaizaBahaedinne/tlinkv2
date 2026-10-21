<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'scope',
        'node_id',
        'status',
        'membership_type',
        'starts_at',
        'ends_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Club (ou unite) de l'organigramme auquel l'adhesion est rattachee.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'node_id');
    }
}
