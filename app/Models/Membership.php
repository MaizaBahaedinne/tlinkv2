<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'motivation_letter',
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

    /**
     * Adhésions liées à un mandat : celles dont la période recoupe la sienne (une adhésion approuvée sans fin
     * reste active sur les mandats suivants). Les demandes en attente sont toujours conservées : elles restent à traiter.
     *
     * @param Builder<Membership> $query
     */
    public function scopeDuringMandate(Builder $query, Mandate $mandate): Builder
    {
        $begin = 'coalesce(memberships.starts_at, memberships.created_at)';
        $from = $mandate->windowStart()->toDateString();
        $before = $mandate->windowEnd()->addDay()->toDateString();

        return $query->where(function (Builder $outer) use ($begin, $from, $before): void {
            $outer->where('memberships.status', 'pending')
                ->orWhere(function (Builder $window) use ($begin, $from, $before): void {
                    $window->whereRaw("{$begin} < ?", [$before])
                        ->where(function (Builder $tail) use ($begin, $from): void {
                            $tail->where('memberships.ends_at', '>=', $from)
                                ->orWhere(fn (Builder $open) => $open->whereNull('memberships.ends_at')->where('memberships.status', 'approved'))
                                ->orWhere(fn (Builder $single) => $single->whereNull('memberships.ends_at')->where('memberships.status', '!=', 'approved')->whereRaw("{$begin} >= ?", [$from]));
                        });
                });
        });
    }
}
