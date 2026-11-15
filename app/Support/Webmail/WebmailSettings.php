<?php

namespace App\Support\Webmail;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Réglages du webmail, saisis dans l'administration (jamais dans le .env) : accès à l'API OVH (pour créer les boîtes),
 * domaine des adresses et serveurs IMAP / SMTP. Le secret et la clé de consommateur sont chiffrés et jamais ré-affichés.
 */
final class WebmailSettings
{
    public const GROUP = 'webmail';

    public const KEY = 'config';

    /** Régions de l'API OVH : clé => [libellé, URL]. */
    public const ENDPOINTS = [
        'ovh-eu' => ['Europe (ovh-eu)', 'https://eu.api.ovh.com/1.0'],
        'ovh-ca' => ['Canada (ovh-ca)', 'https://ca.api.ovh.com/1.0'],
        'ovh-us' => ['États-Unis (ovh-us)', 'https://api.us.ovhcloud.com/1.0'],
    ];

    public const OVH_HOST = 'ssl0.ovh.net';

    private const SECRETS = ['application_secret', 'consumer_key'];

    /**
     * @return array{endpoint: string, application_key: string, application_secret: string, consumer_key: string, domain: string, imap_host: string, imap_port: int, smtp_host: string, smtp_port: int, mailbox_size: int|null}
     */
    public static function get(): array
    {
        $data = self::raw();

        foreach (self::SECRETS as $key) {
            $data[$key] = self::decrypt((string) ($data[$key] ?? ''));
        }

        return [
            'endpoint' => isset(self::ENDPOINTS[$data['endpoint'] ?? '']) ? $data['endpoint'] : 'ovh-eu',
            'application_key' => trim((string) ($data['application_key'] ?? '')),
            'application_secret' => $data['application_secret'],
            'consumer_key' => $data['consumer_key'],
            'domain' => strtolower(trim((string) ($data['domain'] ?? ''))),
            'imap_host' => trim((string) ($data['imap_host'] ?? '')) ?: self::OVH_HOST,
            'imap_port' => (int) ($data['imap_port'] ?? 0) ?: 993,
            'smtp_host' => trim((string) ($data['smtp_host'] ?? '')) ?: self::OVH_HOST,
            'smtp_port' => (int) ($data['smtp_port'] ?? 0) ?: 465,
            'mailbox_size' => ($data['mailbox_size'] ?? null) ? (int) $data['mailbox_size'] : null,
        ];
    }

    /** @param array<string, mixed> $values Un secret vide conserve l'ancien. */
    public static function save(array $values): void
    {
        $current = self::raw();
        $data = [
            'endpoint' => (string) $values['endpoint'],
            'application_key' => trim((string) $values['application_key']),
            'domain' => strtolower(trim((string) $values['domain'])),
            'imap_host' => trim((string) $values['imap_host']),
            'imap_port' => (int) $values['imap_port'],
            'smtp_host' => trim((string) $values['smtp_host']),
            'smtp_port' => (int) $values['smtp_port'],
            'mailbox_size' => ($values['mailbox_size'] ?? null) ? (int) $values['mailbox_size'] : null,
        ];

        foreach (self::SECRETS as $key) {
            $value = trim((string) ($values[$key] ?? ''));
            $data[$key] = $value !== '' ? Crypt::encryptString($value) : ($current[$key] ?? '');
        }

        Setting::query()->updateOrCreate(['group' => self::GROUP, 'key' => self::KEY], ['value' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }

    public static function hasSecret(string $key): bool
    {
        return ! empty(self::raw()[$key]);
    }

    /** L'API OVH est entièrement configurée : T.Link peut créer et supprimer les boîtes. */
    public static function apiReady(): bool
    {
        $config = self::get();

        return $config['application_key'] !== '' && $config['application_secret'] !== '' && $config['consumer_key'] !== '' && $config['domain'] !== '';
    }

    /** Domaine des adresses (ex. fondation-tunivisions.org) : vide tant qu'il n'est pas renseigné. */
    public static function domain(): string
    {
        return self::get()['domain'];
    }

    /** @return array<string, mixed> */
    private static function raw(): array
    {
        try {
            $value = Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            return [];
        }

        $data = $value ? json_decode((string) $value, true) : null;

        return is_array($data) ? $data : [];
    }

    private static function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }
}
