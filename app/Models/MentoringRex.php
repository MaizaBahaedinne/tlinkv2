<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentoringRex extends Model
{
    protected $table = 'mentoring_rex';

    protected $fillable = ['ticket_id', 'author_id', 'context', 'actions', 'outcome', 'lessons', 'recommendations'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(MentoringTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
