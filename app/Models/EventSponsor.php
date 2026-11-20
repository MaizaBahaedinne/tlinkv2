<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSponsor extends Model
{
    protected $fillable = ['event_id', 'level_id', 'name', 'logo_path', 'website_url', 'description', 'sort_order', 'directory_sponsor_id', 'directory_partner_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FoundationEvent::class, 'event_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(EventSponsorLevel::class, 'level_id');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/'.ltrim($this->logo_path, '/')) : null;
    }
}
