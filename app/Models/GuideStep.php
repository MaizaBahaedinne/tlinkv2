<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Étape d'un guide : un titre, un texte, une notice facultative, une capture ou un GIF avec un repère animé,
 * à défaut une icône animée.
 */
class GuideStep extends Model
{
    /** Notices : classes complètes pour Tailwind. */
    public const NOTICES = [
        'info' => ['label' => 'Information', 'icon' => 'fa-circle-info', 'box' => 'border-sky-200 bg-sky-50 text-sky-900'],
        'tip' => ['label' => 'Astuce', 'icon' => 'fa-lightbulb', 'box' => 'border-emerald-200 bg-emerald-50 text-emerald-900'],
        'warning' => ['label' => 'Attention', 'icon' => 'fa-triangle-exclamation', 'box' => 'border-amber-200 bg-amber-50 text-amber-900'],
    ];

    public const DEFAULT_DURATION = 7;

    protected $fillable = ['guide_id', 'position', 'title', 'body', 'notice_type', 'notice', 'icon', 'image_path', 'hotspot_x', 'hotspot_y', 'duration'];

    protected $casts = [
        'position' => 'integer',
        'hotspot_x' => 'integer',
        'hotspot_y' => 'integer',
        'duration' => 'integer',
    ];

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function callout(): ?array
    {
        return filled($this->notice) ? (self::NOTICES[$this->notice_type] ?? self::NOTICES['info']) + ['text' => $this->notice] : null;
    }

    public function iconClass(): string
    {
        return 'fa-solid fa-'.(array_key_exists((string) $this->icon, Guide::ICONS) ? $this->icon : 'hand-pointer');
    }

    public function hasHotspot(): bool
    {
        return $this->image_path !== null && $this->hotspot_x !== null && $this->hotspot_y !== null;
    }
}
