<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Compte à rebours de lancement du site de la Fondation Tunivisions : tant que la date n'est pas atteinte, le site public
 * n'affiche qu'un compte à rebours (avec inscription à la newsletter), sauf aux administrateurs globaux (et à leurs sessions
 * d'incarnation) et aux comptes de la liste d'accès anticipé. T.Link n'est fermé que si l'option « include_app » est cochée.
 * Réglages stockés en base (groupe « site », clé « launch »), modifiables depuis l'administration.
 */
class LaunchGate
{
    public const GROUP = 'site';

    public const KEY = 'launch';

    public const DEFAULT_TITLE = 'Bientôt disponible';

    public const DEFAULT_MESSAGE = 'Le nouveau site de la Fondation Tunivisions ouvre bientôt ses portes. Laissez-nous votre e-mail pour être prévenu(e) dès l’ouverture.';

    /** Chemins restant accessibles pendant la fermeture : connexion, liens de candidature aux postes et santé du serveur. */
    private const OPEN_PATHS = ['up', 'connexion', 'deconnexion', 'auth/*', 'rejoindre/*', 'impersonation/quitter', 'api/v1/auth/login', 'api/v1/auth/logout', 'api/v1/auth/me'];

    /**
     * @return array{enabled: bool, include_app: bool, launch_at: string, title: string, message: string, allowed_user_ids: array<int, int>}
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
            'include_app' => (bool) ($data['include_app'] ?? false),
            'launch_at' => (string) ($data['launch_at'] ?? ''),
            'title' => trim((string) ($data['title'] ?? '')),
            'message' => trim((string) ($data['message'] ?? '')),
            'allowed_user_ids' => array_values(array_unique(array_map('intval', (array) ($data['allowed_user_ids'] ?? [])))),
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
        $locales = array_keys(config('site.locales'));
        $newsletter = [];

        // Inscription et désinscription à la newsletter : le compte à rebours en propose le formulaire.
        foreach ($locales as $locale) {
            $newsletter[] = $locale.'/newsletter';
            $newsletter[] = $locale.'/newsletter/desinscription/*';
        }

        return $request->is([...self::OPEN_PATHS, ...$newsletter]);
    }

    /** Page du site vitrine (racine et préfixes de langue) ; le reste (feed, profil, API…) appartient à T.Link. */
    public static function isSitePath(Request $request): bool
    {
        // Avec des sous-domaines dédiés, la racine et les langues n'appartiennent au site vitrine que sur son propre hôte.
        if (Domains::enabled() && ! in_array(Domains::keyForHost($request->getHost()), [null, 'site'], true)) {
            return false;
        }

        $patterns = ['/'];

        foreach (array_keys(config('site.locales')) as $locale) {
            $patterns[] = $locale;
            $patterns[] = $locale.'/*';
        }

        return $request->is($patterns);
    }

    /** Le compte à rebours s'applique à cette requête : le site toujours, T.Link seulement si l'option est cochée. */
    public static function covers(Request $request, array $settings): bool
    {
        return $settings['include_app'] || self::isSitePath($request);
    }

    /** Administrateur global, administrateur en train d'incarner un autre utilisateur, ou compte autorisé dans la liste d'accès anticipé. */
    public static function bypasses(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return $request->hasSession() && $request->session()->has('impersonator_id')
            || (RoleSimulator::active() && RoleSimulator::isGlobalAdmin($user))
            || in_array($user->id, self::settings()['allowed_user_ids'], true)
            || AdminContext::of($request)->isGlobal('entity.manage');
    }
}
