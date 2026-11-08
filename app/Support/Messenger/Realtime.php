<?php

namespace App\Support\Messenger;

use App\Events\MessengerUpdated;
use App\Models\MessengerConversation;
use App\Models\MessengerParticipant;

/**
 * Temps réel de la messagerie (Laravel Reverb). Entièrement facultatif : sans connexion Reverb configurée, rien n'est diffusé
 * et le navigateur continue d'interroger le serveur (la même interrogation sert de repli si la connexion WebSocket tombe).
 *
 * Les événements ne transportent aucun contenu : ils préviennent seulement les participants qu'il y a du nouveau,
 * et chaque navigateur relit alors les messages par la voie habituelle (droits et blocages vérifiés côté serveur).
 */
final class Realtime
{
    /** Diffusion active : pilote Reverb avec clés et hôte renseignés. */
    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'))
            && filled(config('broadcasting.connections.reverb.secret'))
            && filled(config('broadcasting.connections.reverb.options.host'));
    }

    /**
     * Paramètres de connexion transmis au navigateur, ou null si le temps réel est inactif.
     *
     * @return array{key: string, host: string, port: int, tls: bool, auth: string}|null
     */
    public static function clientConfig(): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $options = (array) config('broadcasting.connections.reverb.options');

        return [
            'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => (string) $options['host'],
            'port' => (int) ($options['port'] ?? 443),
            'tls' => ($options['scheme'] ?? 'https') === 'https',
            'auth' => '/broadcasting/auth',
        ];
    }

    /** Prévient les participants (sauf l'auteur de l'action) après l'envoi de la réponse, sans jamais gêner la requête. */
    public static function notify(MessengerConversation $conversation, string $type, ?int $exceptUserId = null): void
    {
        if (! self::enabled()) {
            return;
        }

        $conversationId = $conversation->id;

        app()->terminating(function () use ($conversationId, $type, $exceptUserId): void {
            try {
                $userIds = MessengerParticipant::query()->where('conversation_id', $conversationId)->whereNull('left_at')
                    ->when($exceptUserId !== null, fn ($query) => $query->where('user_id', '!=', $exceptUserId))
                    ->pluck('user_id')->map(fn ($id) => (int) $id)->all();

                event(new MessengerUpdated($conversationId, $type, $userIds, $type === 'typing' ? $exceptUserId : null));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
