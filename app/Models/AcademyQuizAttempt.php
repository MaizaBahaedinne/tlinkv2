<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyQuizAttempt extends Model
{
    protected $fillable = ['user_id', 'quiz_id', 'status', 'score_percent', 'snapshot', 'answers', 'started_at', 'submitted_at'];

    protected $casts = [
        'snapshot' => 'array',
        'answers' => 'array',
        'score_percent' => 'integer',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(AcademyQuiz::class, 'quiz_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }
}
