<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentoringEvent extends Model
{
    /** Types d'entrées visibles par le demandeur (notes internes et REX restent réservés à l'équipe de mentors). */
    public const PUBLIC_KINDS = ['comment', 'status', 'qualification', 'assignment'];

    protected $fillable = ['ticket_id', 'user_id', 'kind', 'body', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(MentoringTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
