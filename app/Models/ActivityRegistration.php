<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRegistration extends Model
{
    public const STATUSES = ['registered' => 'Inscrit', 'waitlisted' => 'Liste d’attente', 'cancelled' => 'Annulée'];

    protected $fillable = ['activity_id', 'user_id', 'status', 'answers', 'checked_in_at'];

    protected $casts = ['answers' => 'array', 'checked_in_at' => 'datetime'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
