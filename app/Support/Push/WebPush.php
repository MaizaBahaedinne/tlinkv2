<?php

namespace App\Support\Push;

use App\Models\Setting;
use App\Models\WebPushSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush as WebPushClient;

/**
 * Notifications push dans le navigateur (standard Web Push avec clés VAPID), sans compte tiers à configurer.
 * La paire de clés VAPID est générée au premier usage et conservée en base (réglage « push » / « vapid », clé privée chiffrée).
 */
final class WebPush
{
    public const GROUP = 'push';

    public const KEY = 'vapid';

    /** Services de notification des navigateurs : le serveur n'envoie jamais vers une autre adresse (protection contre le SSRF). */
    private const ALLOWED_HOST_SUFFIXES = ['googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];

    public static function isAllowedEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{publicKey: string, privateKey: string}|null */
    private static function keys(): ?array
    {
        try {
            $setting = Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->first();

            if (! $setting) {
                $keys = VAPID::createVapidKeys();
                $setting = Setting::query()->firstOrCreate(
                    ['group' => self::GROUP, 'key' => self::KEY],
                    ['value' => json_encode(['public' => $keys['publicKey'], 'private' => Crypt::encryptString($keys['privateKey'])])],
                );
            }

            $data = json_decode((string) $setting->value, true);

            return is_array($data) && ! empty($data['public']) && ! empty($data['private'])
                ? ['publicKey' => (string) $data['public'], 'privateKey' => Crypt::decryptString((string) $data['private'])]
                : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Clé publique transmise au navigateur pour s'abonner ; null si les clés sont indisponibles. */
    public static function publicKey(): ?string
    {
        $cached = Cache::get('web-push:public-key');

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $key = self::keys()['publicKey'] ?? null;

        if ($key !== null) {
            Cache::forever('web-push:public-key', $key);
        }

        return $key;
    }

    public static function enabled(): bool
    {
        return self::publicKey() !== null;
    }

    /** @return WebPushClient (remplaçable dans le conteneur, notamment par les tests) */
    private static function client(array $keys): object
    {
        $subject = filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) ? 'mailto:'.config('mail.from.address') : (string) config('app.url');

        return app()->make(WebPushClient::class, [
            'auth' => ['VAPID' => ['subject' => $subject, 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey']]],
            'defaultOptions' => ['TTL' => 3600, 'urgency' => 'normal'],
            'client' => new \GuzzleHttp\Client(['timeout' => 8]),
        ]);
    }

    /**
     * Envoie la notification à chaque navigateur abonné ; les abonnements expirés (404/410) sont supprimés.
     *
     * @param  iterable<WebPushSubscription>  $subscriptions
     * @return list<array{subscription: int, ok: bool, error: string}>
     */
    public static function send(iterable $subscriptions, string $title, string $body, string $url, ?string $tag = null, string $icon = '/app-icons/link/icon-192.png'): array
    {
        $keys = self::keys();
        $subscriptions = collect($subscriptions)->keyBy(fn (WebPushSubscription $s): string => $s->endpoint);

        if ($keys === null || $subscriptions->isEmpty()) {
            return [];
        }

        $payload = (string) json_encode([
            'title' => mb_strimwidth($title, 0, 80, '…'),
            'body' => mb_strimwidth($body, 0, 160, '…'),
            'url' => $url,
            'tag' => $tag,
            'icon' => $icon,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $client = self::client($keys);
        $results = [];

        foreach ($subscriptions as $subscription) {
            $client->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), $payload);
        }

        foreach ($client->flush() as $report) {
            $subscription = $subscriptions->get($report->getEndpoint());

            if (! $report->isSuccess()) {
                Log::channel('push')->warning('Web Push : envoi refusé ('.$report->getReason().')');

                if ($subscription && $report->isSubscriptionExpired()) {
                    $subscription->delete();
                }
            }

            $results[] = ['subscription' => (int) $subscription?->id, 'ok' => $report->isSuccess(), 'error' => $report->isSuccess() ? '' : $report->getReason()];
        }

        return $results;
    }

    /** Notifie les navigateurs abonnés des comptes donnés, dans l'application $section (T.Link, Messenger…). */
    public static function sendToUsers(array $userIds, string $title, string $body, string $url, ?string $tag = null, string $icon = '/app-icons/link/icon-192.png', string $section = 'app'): array
    {
        $subscriptions = WebPushSubscription::query()->whereIn('user_id', $userIds)->forSection($section)->get();

        return $subscriptions->isEmpty() ? [] : self::send($subscriptions, $title, $body, $url, $tag, $icon);
    }
}
