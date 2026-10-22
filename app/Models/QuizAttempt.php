<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    public const STATUSES = [
        'in_progress' => 'En cours',
        'passed' => 'Réussi',
        'failed' => 'Échoué',
        'expired' => 'Temps écoulé',
    ];

    protected $fillable = ['user_id', 'status', 'snapshot', 'answers', 'score', 'total', 'started_at', 'expires_at', 'submitted_at'];

    protected $casts = [
        'snapshot' => 'array',
        'answers' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }
}
