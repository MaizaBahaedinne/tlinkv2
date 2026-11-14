<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Interlocuteur d'un sponsor ou partenaire. */
class SponsorContact extends Model
{
    protected $fillable = ['sponsor_id', 'name', 'job_title', 'email', 'phone', 'notes', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }
}
