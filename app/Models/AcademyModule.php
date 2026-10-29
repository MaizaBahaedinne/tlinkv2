<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademyModule extends Model
{
    protected $fillable = ['course_id', 'title', 'description', 'badge_id', 'sort_order'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'course_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(AcademyLesson::class, 'module_id')->orderBy('sort_order')->orderBy('id');
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
