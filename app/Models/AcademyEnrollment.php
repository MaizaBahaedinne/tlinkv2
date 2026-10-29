<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyEnrollment extends Model
{
    protected $fillable = ['user_id', 'course_id', 'source', 'enrolled_at', 'completed_at', 'certificate_code'];

    protected $casts = ['enrolled_at' => 'datetime', 'completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'course_id');
    }
}
