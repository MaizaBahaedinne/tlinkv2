<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyLesson extends Model
{
    public const KINDS = ['text' => 'Texte', 'video' => 'Vidéo'];

    protected $fillable = ['module_id', 'title', 'kind', 'video_url', 'content', 'duration_minutes', 'points', 'is_preview', 'sort_order'];

    protected $casts = ['is_preview' => 'boolean', 'points' => 'integer', 'duration_minutes' => 'integer'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(AcademyModule::class, 'module_id');
    }

    /**
     * URL à intégrer dans un iframe : uniquement YouTube ou Vimeo (jamais une adresse libre).
     */
    public function embedUrl(): ?string
    {
        $url = (string) $this->video_url;

        if (preg_match('~^https://(?:www\.)?(?:youtube\.com/watch\?(?:[^#]*&)?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~', $url, $match) === 1) {
            return 'https://www.youtube-nocookie.com/embed/'.$match[1];
        }

        if (preg_match('~^https://(?:www\.)?vimeo\.com/(?:video/)?(\d+)~', $url, $match) === 1) {
            return 'https://player.vimeo.com/video/'.$match[1];
        }

        return null;
    }
}
