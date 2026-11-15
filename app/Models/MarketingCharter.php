<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Charte d'usage des réseaux sociaux : « default » pour toutes les entités, ou une charte propre à « club:12 » / « scope:university ». */
class MarketingCharter extends Model
{
    protected $fillable = ['entity', 'title', 'body', 'version', 'updated_by'];

    protected $casts = ['version' => 'integer'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
