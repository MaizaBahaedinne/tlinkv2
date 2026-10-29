<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conversation T.Link Messenger : directe (2 membres) ou de groupe. */
class MessengerConversation extends Model
{
    protected $fillable = ['type', 'title', 'created_by', 'direct_key', 'last_message_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function participants(): HasMany
    {
        return $this->hasMany(MessengerParticipant::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessengerMessage::class, 'conversation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }
}
