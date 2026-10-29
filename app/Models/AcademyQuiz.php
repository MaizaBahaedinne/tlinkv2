<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Quiz d'un module, ou quiz final d'un cours (module_id nul). */
class AcademyQuiz extends Model
{
    protected $fillable = ['course_id', 'module_id', 'title', 'intro', 'pass_percent', 'questions_per_attempt', 'is_required'];

    protected $casts = ['pass_percent' => 'integer', 'questions_per_attempt' => 'integer', 'is_required' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'course_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AcademyModule::class, 'module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AcademyQuizQuestion::class, 'quiz_id')->orderBy('sort_order')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AcademyQuizAttempt::class, 'quiz_id');
    }

    /** Nombre de questions posées à chaque tentative. */
    public function drawSize(int $available): int
    {
        return $this->questions_per_attempt !== null && $this->questions_per_attempt > 0
            ? min($this->questions_per_attempt, $available)
            : $available;
    }
}
