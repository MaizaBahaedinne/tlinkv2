<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_type',
        'requester_id',
        'approvable_type',
        'approvable_id',
        'assigned_reviewer_id',
        'status',
        'submitted_at',
        'decided_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_id');
    }

    /**
     * Demandes en attente qui concernent l'utilisateur : sans validateur, assignées à lui, d'une entité dont il est validateur
     * désigné ; un administrateur global les voit toutes (il peut statuer sur chacune).
     *
     * @param \Illuminate\Database\Eloquent\Builder<ApprovalRequest> $query
     */
    public function scopeAwaiting($query, User $user)
    {
        $query->where('status', 'pending');

        if (\App\Support\OrgAccess::responsibility($user, 'approval.review')['all']) {
            return $query;
        }

        $scopes = \App\Support\ScopeValidators::scopesOf($user->id);

        return $query->where(function ($inner) use ($user, $scopes): void {
            $inner->whereNull('assigned_reviewer_id')->orWhere('assigned_reviewer_id', $user->id);

            if ($scopes !== []) {
                $inner->orWhere(fn ($q) => $q->where('approvable_type', Membership::class)
                    ->whereIn('approvable_id', Membership::query()->whereIn('scope', $scopes)->select('id')))
                    ->orWhere(fn ($q) => $q->where('approvable_type', MandateParticipation::class)
                        ->whereIn('approvable_id', MandateParticipation::query()->whereIn('scope', $scopes)->select('id')));
            }
        });
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }
}
