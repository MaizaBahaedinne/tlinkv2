<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademyQuizQuestion extends Model
{
    public const TYPES = ['single' => 'Une seule bonne réponse', 'multiple' => 'Plusieurs bonnes réponses'];

    protected $fillable = ['quiz_id', 'text', 'type', 'explanation', 'sort_order'];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(AcademyQuiz::class, 'quiz_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(AcademyQuizOption::class, 'question_id')->orderBy('sort_order')->orderBy('id');
    }
}
