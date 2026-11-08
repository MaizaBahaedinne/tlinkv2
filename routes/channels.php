<?php

use App\Models\MessengerConversation;
use App\Support\Messenger\Messenger;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Messagerie : un membre n'écoute que les conversations dont il fait partie et son propre salon.
Broadcast::channel('messenger.conversation.{id}', function ($user, $id) {
    $conversation = MessengerConversation::query()->find($id);

    return $conversation !== null && Messenger::participant($conversation, $user) !== null;
});

Broadcast::channel('messenger.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
