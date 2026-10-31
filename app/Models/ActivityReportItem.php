<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Élément marketing du compte rendu : affiche ou preuve (fichier), ou retombée média (lien). */
class ActivityReportItem extends Model
{
    protected $fillable = ['report_id', 'kind', 'label', 'url', 'path'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ActivityReport::class, 'report_id');
    }

    public function fileUrl(): ?string
    {
        return ActivityReport::fileUrl($this->path);
    }
}
