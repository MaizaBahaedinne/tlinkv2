<?php

namespace App\Support\Messenger;

use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerParticipant;
use App\Models\PushDevice;
use App\Models\User;
use App\Support\Domains;
use App\Support\Push\Fcm;

/**
 * Notification push d'un nouveau message aux autres participants (conversation directe ou groupe), après l'envoi de la réponse.
 */
final class MessengerPush
{
    public static function notify(MessengerConversation $conversation, MessengerMessage $message, User $sender): void
    {
        if (! Fcm::enabled()) {
            return;
        }

        $preview = match (true) {
            $message->body !== null && $message->body !== '' => $message->body,
            $message->audio_path !== null => 'Message vocal',
            default => 'Photo',
        };

        $isGroup = $conversation->isGroup();
        $title = $isGroup ? (string) ($conversation->title ?: 'Groupe') : $sender->name;
        $body = $isGroup ? $sender->name.' : '.$preview : $preview;
        $conversationId = $conversation->id;
        $senderId = $sender->id;
        $path = route('messenger.show', $conversation, false);

        app()->terminating(function () use ($conversationId, $senderId, $title, $body, $path): void {
            try {
                $userIds = MessengerParticipant::query()->where('conversation_id', $conversationId)->whereNull('left_at')
                    ->where('user_id', '!=', $senderId)->pluck('user_id')->all();

                $devices = PushDevice::query()->whereIn('user_id', $userIds)->get();

                if ($devices->isNotEmpty()) {
                    Fcm::send($devices, $title, $body, ['url' => Domains::url('messenger', $path), 'conversation' => $conversationId], 'conversation-'.$conversationId);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
