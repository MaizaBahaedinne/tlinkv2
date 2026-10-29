<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerMessage extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'body', 'attachment_path', 'attachment_name', 'deleted_at'];

    protected $casts = ['deleted_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessengerConversation::class, 'conversation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
