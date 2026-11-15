<?php

namespace App\Support\Webmail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Client minimal de l'API OVH pour les boîtes d'un MX Plan (« /email/domain/{domaine}/account »).
 * Les clés viennent des réglages du webmail ; elles ne sont jamais écrites dans les journaux.
 */
class OvhApi
{
    public function configured(): bool
    {
        return WebmailSettings::apiReady();
    }

    /** Vérifie les clés et l'accès au domaine : renvoie les noms des boîtes existantes. */
    public function accounts(): array
    {
        $domain = WebmailSettings::domain();

        return (array) $this->call('GET', '/email/domain/'.rawurlencode($domain).'/account');
    }

    public function createAccount(string $name, string $password, string $description = ''): void
    {
        $config = WebmailSettings::get();
        $body = ['accountName' => $name, 'password' => $password];

        if ($description !== '') {
            $body['description'] = Str::limit($description, 100, '');
        }

        if ($config['mailbox_size']) {
            $body['size'] = $config['mailbox_size'];
        }

        $this->call('POST', '/email/domain/'.rawurlencode($config['domain']).'/account', $body);
    }

    public function changePassword(string $name, string $password): void
    {
        $this->call('POST', '/email/domain/'.rawurlencode(WebmailSettings::domain()).'/account/'.rawurlencode($name).'/changePassword', ['password' => $password]);
    }

    public function deleteAccount(string $name): void
    {
        $this->call('DELETE', '/email/domain/'.rawurlencode(WebmailSettings::domain()).'/account/'.rawurlencode($name));
    }

    /** Mot de passe aléatoire accepté par OVH (9 à 30 caractères, lettres et chiffres, sans accent ni espace). */
    public static function generatePassword(int $length = 24): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789'];
        $all = implode('', $sets);
        $chars = array_map(fn (string $set): string => $set[random_int(0, strlen($set) - 1)], $sets);

        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($chars);

        return implode('', $chars);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function call(string $method, string $path, ?array $body = null): mixed
    {
        if (! WebmailSettings::apiReady()) {
            throw new WebmailException('La connexion à l’API OVH n’est pas configurée (clés et domaine).');
        }

        $config = WebmailSettings::get();
        $base = WebmailSettings::ENDPOINTS[$config['endpoint']][1];
        $url = $base.$path;
        $json = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $timestamp = (int) Http::timeout(10)->get($base.'/auth/time')->throw()->body();
            $signature = '$1$'.sha1($config['application_secret'].'+'.$config['consumer_key'].'+'.$method.'+'.$url.'+'.$json.'+'.$timestamp);

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Ovh-Application' => $config['application_key'],
                    'X-Ovh-Consumer' => $config['consumer_key'],
                    'X-Ovh-Timestamp' => (string) $timestamp,
                    'X-Ovh-Signature' => $signature,
                    'Accept' => 'application/json',
                ])
                ->withBody($json, 'application/json')
                ->send($method, $url);
        } catch (ConnectionException) {
            throw new WebmailException('L’API OVH ne répond pas : réessayez dans un instant.');
        } catch (\Throwable $exception) {
            throw new WebmailException('L’API OVH est injoignable ('.Str::limit($exception->getMessage(), 120).').');
        }

        if ($response->failed()) {
            $message = (string) ($response->json('message') ?? 'Erreur '.$response->status());

            throw new WebmailException('OVH a refusé la demande : '.Str::limit(strip_tags($message), 250));
        }

        return $response->json();
    }
}
