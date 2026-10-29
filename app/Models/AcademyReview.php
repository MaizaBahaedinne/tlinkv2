<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Avis d'un membre sur un cours Academy et sur son formateur. */
class AcademyReview extends Model
{
    protected $fillable = ['course_id', 'user_id', 'course_rating', 'course_comment', 'instructor_rating', 'instructor_comment', 'is_hidden'];

    protected $casts = ['course_rating' => 'integer', 'instructor_rating' => 'integer', 'is_hidden' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'course_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
