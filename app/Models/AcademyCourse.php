<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

/** Cours de Tunivisions Academy (modules puis leçons). */
class AcademyCourse extends Model
{
    public const LEVELS = ['beginner' => 'Débutant', 'intermediate' => 'Intermédiaire', 'advanced' => 'Avancé'];

    public const STATUSES = ['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'];

    protected $fillable = ['title', 'slug', 'summary', 'description', 'cover_path', 'level', 'instructor_name', 'price', 'included_in_plans', 'badge_id', 'completion_points', 'status', 'sort_order'];

    protected $casts = ['price' => 'decimal:2', 'included_in_plans' => 'boolean', 'completion_points' => 'integer'];

    public function modules(): HasMany
    {
        return $this->hasMany(AcademyModule::class, 'course_id')->orderBy('sort_order')->orderBy('id');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(AcademyLesson::class, AcademyModule::class, 'course_id', 'module_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(AcademyEnrollment::class, 'course_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(AcademyQuiz::class, 'course_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AcademySession::class, 'course_id');
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function isFree(): bool
    {
        return $this->price === null || (float) $this->price <= 0;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? self::LEVELS['beginner'];
    }

    /** « Gratuit » ou « 120 TND ». */
    public function priceLabel(): string
    {
        return $this->isFree() ? 'Gratuit' : self::money((float) $this->price);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public static function money(float $amount, string $currency = 'TND'): string
    {
        return rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',').' '.$currency;
    }
}
