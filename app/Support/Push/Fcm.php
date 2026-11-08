<?php

namespace App\Support\Push;

use App\Models\PushDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Envoi de notifications push via Firebase Cloud Messaging (API HTTP v1), sans dépendance supplémentaire.
 * Gratuit, mais réservé aux comptes de service : FCM_CREDENTIALS pointe vers le fichier JSON du compte de service Firebase.
 * Sans ce fichier, rien n'est envoyé (aucune erreur).
 */
final class Fcm
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri?: string}|null */
    private static function credentials(): ?array
    {
        $path = (string) config('services.fcm.credentials');

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && ! empty($data['project_id']) && ! empty($data['client_email']) && ! empty($data['private_key']) ? $data : null;
    }

    public static function enabled(): bool
    {
        return self::credentials() !== null;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** Jeton d'accès OAuth2 (mis en cache un peu moins d'une heure). */
    private static function accessToken(array $credentials): ?string
    {
        return Cache::remember('fcm:access-token:'.md5($credentials['client_email']), 3300, function () use ($credentials): ?string {
            $uri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();
            $claims = self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.self::base64Url((string) json_encode([
                'iss' => $credentials['client_email'], 'scope' => self::SCOPE, 'aud' => $uri, 'iat' => $now, 'exp' => $now + 3600,
            ]));

            if (! openssl_sign($claims, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $response = Http::asForm()->timeout(8)->post($uri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $claims.'.'.self::base64Url($signature),
            ]);

            return $response->successful() ? ($response->json('access_token') ?: null) : null;
        });
    }

    /**
     * Envoie la notification à chaque appareil ; les jetons que Firebase déclare périmés sont supprimés.
     *
     * @param  iterable<PushDevice>  $devices
     * @param  array<string, scalar>  $data  valeurs transmises à l'application (adresse à ouvrir, conversation…)
     */
    public static function send(iterable $devices, string $title, string $body, array $data = [], ?string $tag = null): void
    {
        $credentials = self::credentials();

        if ($credentials === null) {
            return;
        }

        $token = self::accessToken($credentials);

        if ($token === null) {
            Cache::forget('fcm:access-token:'.md5($credentials['client_email']));

            return;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/'.$credentials['project_id'].'/messages:send';
        $data = array_map('strval', $data);

        foreach ($devices as $device) {
            $response = Http::withToken($token)->timeout(8)->post($url, ['message' => [
                'token' => $device->token,
                'notification' => ['title' => mb_strimwidth($title, 0, 80, '…'), 'body' => mb_strimwidth($body, 0, 160, '…')],
                'data' => $data,
                'android' => ['priority' => 'HIGH', 'notification' => array_filter(['channel_id' => 'messages', 'tag' => $tag])],
                'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
            ]]);

            $status = (string) $response->json('error.status');

            if ($response->status() === 404 || in_array($status, ['NOT_FOUND', 'UNREGISTERED'], true)) {
                $device->delete();
            }
        }
    }
}
