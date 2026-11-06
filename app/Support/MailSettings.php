<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * Boîtes d'envoi des applications (Admin > Messagerie) : une adresse par application, stockée en base.
 * Le mot de passe SMTP est chiffré (clé de l'application) et n'est jamais ré-affiché ni écrit dans le journal.
 *
 * T.Link envoie par défaut ; les autres applications ont leur propre expéditeur dès qu'elles sont configurées
 * (`MailSettings::mailer('academy')`, `MailSettings::from('academy')`), sinon elles retombent sur celui de T.Link.
 */
final class MailSettings
{
    public const GROUP = 'mail';

    public const KEY = 'profiles';

    public const DEFAULT_PROFILE = 'tlink';

    /** Applications : clé => libellé. */
    public const PROFILES = [
        'tlink' => 'T.Link',
        'academy' => 'Tunivisions Academy',
        'store' => 'Tunivisions Stores',
        'chapters' => 'Chapitres',
        'projects' => 'Projets',
    ];

    /** Modes de connexion : clé => [libellé, schéma Symfony Mailer, port conseillé]. */
    public const ENCRYPTIONS = [
        'ssl' => ['SSL (port 465)', 'smtps', 465],
        'tls' => ['STARTTLS (port 587)', 'smtp', 587],
    ];

    /** Serveur SMTP d'un MX Plan OVH. */
    public const OVH_HOST = 'ssl0.ovh.net';

    /**
     * @return array{enabled: bool, host: string, port: int, encryption: string, username: string, password: string, from_address: string, from_name: string}
     */
    public static function profile(string $profile, ?array $all = null): array
    {
        $data = ($all ?? self::all())[$profile] ?? [];

        $password = '';

        if (! empty($data['password'])) {
            try {
                $password = Crypt::decryptString((string) $data['password']);
            } catch (\Throwable) {
                $password = '';
            }
        }

        $encryption = (string) ($data['encryption'] ?? 'ssl');

        return [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'host' => trim((string) ($data['host'] ?? self::OVH_HOST)),
            'port' => (int) ($data['port'] ?? self::ENCRYPTIONS['ssl'][2]),
            'encryption' => isset(self::ENCRYPTIONS[$encryption]) ? $encryption : 'ssl',
            'username' => trim((string) ($data['username'] ?? '')),
            'password' => $password,
            'from_address' => trim((string) ($data['from_address'] ?? '')),
            'from_name' => trim((string) ($data['from_name'] ?? '')),
        ];
    }

    /**
     * Enregistre une application. Un mot de passe vide conserve l'ancien.
     *
     * @param array<string, mixed> $values
     */
    public static function save(string $profile, array $values): void
    {
        $all = self::all();
        $current = $all[$profile] ?? [];

        $password = (string) ($values['password'] ?? '');

        $all[$profile] = [
            'enabled' => (bool) ($values['enabled'] ?? false),
            'host' => trim((string) ($values['host'] ?? '')),
            'port' => (int) ($values['port'] ?? 0),
            'encryption' => (string) ($values['encryption'] ?? 'ssl'),
            'username' => trim((string) ($values['username'] ?? '')),
            'password' => $password !== '' ? Crypt::encryptString($password) : ($current['password'] ?? ''),
            'from_address' => trim((string) ($values['from_address'] ?? '')),
            'from_name' => trim((string) ($values['from_name'] ?? '')),
        ];

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($all, JSON_UNESCAPED_UNICODE)],
        );
    }

    /** Un mot de passe est enregistré pour cette application. */
    public static function hasPassword(string $profile): bool
    {
        return ! empty(self::all()[$profile]['password']);
    }

    /** Configuration SMTP prête pour Laravel (`mail.mailers.*`). */
    public static function mailerConfig(array $profile): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => self::ENCRYPTIONS[$profile['encryption']][1] ?? 'smtps',
            'host' => $profile['host'],
            'port' => $profile['port'],
            'username' => $profile['username'],
            'password' => $profile['password'],
            'timeout' => 15,
        ];
    }

    /** L'application envoie avec sa propre adresse : activée et complète. */
    public static function usable(string $profile, ?array $all = null): bool
    {
        $data = self::profile($profile, $all);

        return $data['enabled'] && $data['host'] !== '' && $data['username'] !== '' && $data['password'] !== '' && $data['from_address'] !== '';
    }

    /** Nom du mailer à utiliser pour une application (celui de T.Link tant qu'elle n'a pas le sien). */
    public static function mailer(string $profile): ?string
    {
        if ($profile !== self::DEFAULT_PROFILE && self::usable($profile)) {
            return 'app-'.$profile;
        }

        return self::usable(self::DEFAULT_PROFILE) ? 'smtp' : null;
    }

    /** Expéditeur d'une application : [adresse, nom]. */
    public static function from(string $profile): array
    {
        $key = $profile !== self::DEFAULT_PROFILE && self::usable($profile) ? $profile : self::DEFAULT_PROFILE;
        $data = self::profile($key);

        return $data['from_address'] !== '' ? [$data['from_address'], $data['from_name'] ?: config('mail.from.name')] : [config('mail.from.address'), config('mail.from.name')];
    }

    /**
     * Applique les boîtes enregistrées à la configuration de Laravel : T.Link devient l'envoi par défaut,
     * chaque autre application configurée reçoit son mailer `app-<clé>`. Sans configuration, le .env reste en vigueur.
     */
    public static function apply(): void
    {
        $all = self::all();

        if ($all === []) {
            return;
        }

        foreach (array_keys(self::PROFILES) as $profile) {
            if (! self::usable($profile, $all)) {
                continue;
            }

            $data = self::profile($profile, $all);

            if ($profile === self::DEFAULT_PROFILE) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp' => self::mailerConfig($data),
                    'mail.from.address' => $data['from_address'],
                    'mail.from.name' => $data['from_name'] ?: config('mail.from.name'),
                ]);

                continue;
            }

            config(['mail.mailers.app-'.$profile => self::mailerConfig($data)]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function all(): array
    {
        try {
            $value = Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            return [];
        }

        $data = $value ? json_decode((string) $value, true) : null;

        return is_array($data) ? $data : [];
    }
}
