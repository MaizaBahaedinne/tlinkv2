<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signal « du nouveau dans cette conversation » (message, suppression, saisie en cours). Sans contenu : les navigateurs relisent les messages.
 * Diffusé sur le salon de la conversation (fenêtre ouverte) et sur le salon personnel de chaque participant (liste, pastille).
 */
class MessengerUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<int, int>  $userIds  participants à prévenir
     */
    public function __construct(public int $conversationId, public string $type, public array $userIds, public ?int $by = null)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('messenger.conversation.'.$this->conversationId)];

        foreach ($this->userIds as $id) {
            $channels[] = new PrivateChannel('messenger.user.'.$id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'messenger.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['conversation' => $this->conversationId, 'type' => $this->type, 'by' => $this->by];
    }
}
