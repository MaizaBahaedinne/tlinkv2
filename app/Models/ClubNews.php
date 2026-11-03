<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Actualité publiée par un club sur sa page profil. */
class ClubNews extends Model
{
    protected $table = 'club_news';

    protected $fillable = ['club_id', 'user_id', 'title', 'body', 'cover_path', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function club(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'club_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
