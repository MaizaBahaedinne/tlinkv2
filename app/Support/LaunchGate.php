<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Compte à rebours de lancement : tant que la date n'est pas atteinte, le site public et T.Link
 * n'affichent qu'un compte à rebours, sauf aux administrateurs globaux (et à leurs sessions d'incarnation).
 * Réglages stockés en base (groupe « site », clé « launch »), modifiables depuis l'administration.
 */
class LaunchGate
{
    public const GROUP = 'site';

    public const KEY = 'launch';

    public const DEFAULT_TITLE = 'Bientôt disponible';

    public const DEFAULT_MESSAGE = 'T.Link, la plateforme de la Fondation Tunivisions, ouvre bientôt ses portes. Rendez-vous à l’ouverture.';

    /** Chemins restant accessibles pendant la fermeture : connexion, liens de candidature aux postes et santé du serveur. */
    private const OPEN_PATHS = ['up', 'connexion', 'deconnexion', 'auth/*', 'rejoindre/*', 'impersonation/quitter', 'api/v1/auth/login', 'api/v1/auth/logout', 'api/v1/auth/me'];

    /**
     * @return array{enabled: bool, launch_at: string, title: string, message: string}
     */
    public static function settings(): array
    {
        try {
            $value = Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            $value = null;
        }

        $data = $value ? json_decode((string) $value, true) : null;
        $data = is_array($data) ? $data : [];

        return [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'launch_at' => (string) ($data['launch_at'] ?? ''),
            'title' => trim((string) ($data['title'] ?? '')),
            'message' => trim((string) ($data['message'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function save(array $values): Setting
    {
        return Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($values, JSON_UNESCAPED_UNICODE)],
        );
    }

    /** Date de lancement (fuseau de l'application), ou null si absente ou illisible. */
    public static function launchAt(?array $settings = null): ?CarbonImmutable
    {
        $raw = ($settings ?? self::settings())['launch_at'];

        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Le site est fermé : réglage actif et date de lancement pas encore atteinte. */
    public static function isClosed(?array $settings = null): bool
    {
        $settings ??= self::settings();

        if (! $settings['enabled']) {
            return false;
        }

        $launchAt = self::launchAt($settings);

        // Sans date valide on reste fermé : c'est l'administrateur qui ouvre en désactivant le réglage.
        return $launchAt === null || now()->lt($launchAt);
    }

    public static function isOpenPath(Request $request): bool
    {
        return $request->is(self::OPEN_PATHS);
    }

    /** Administrateur global, ou administrateur en train d'incarner un autre utilisateur. */
    public static function bypasses(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return $request->hasSession() && $request->session()->has('impersonator_id')
            || AdminContext::of($request)->isGlobal('entity.manage');
    }
}
