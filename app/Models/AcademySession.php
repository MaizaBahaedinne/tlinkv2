<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Session en direct du calendrier Academy. */
class AcademySession extends Model
{
    protected $fillable = ['course_id', 'title', 'description', 'starts_at', 'ends_at', 'location', 'meeting_url', 'is_published'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_published' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'course_id');
    }

    /** Lien de connexion en http(s) uniquement. */
    public function safeMeetingUrl(): ?string
    {
        return $this->meeting_url && preg_match('#^https?://#i', $this->meeting_url) === 1 ? $this->meeting_url : null;
    }
}
