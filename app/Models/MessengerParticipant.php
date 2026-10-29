<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerParticipant extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'role', 'last_read_message_id', 'left_at'];

    protected $casts = ['last_read_message_id' => 'integer', 'left_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessengerConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
