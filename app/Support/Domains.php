<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;

/**
 * Répartition du site entre sous-domaines (site vitrine, T.Link, Store, Academy, Messenger). Tout est optionnel : sans hôte configuré,
 * l'application se comporte comme un site unique.
 */
class Domains
{
    public const KEYS = ['site', 'app', 'store', 'academy', 'messenger'];

    /** Les applications (une par sous-domaine), cloisonnées entre elles. */
    public const APPS = ['app', 'store', 'academy', 'messenger'];

    /** Hôte configuré pour une section, ou null. */
    public static function host(string $key): ?string
    {
        $host = config("domains.hosts.$key");

        return is_string($host) && trim($host) !== '' ? strtolower(trim($host)) : null;
    }

    /** Au moins un sous-domaine dédié est configuré. */
    public static function enabled(): bool
    {
        foreach (['site', 'store', 'academy', 'messenger'] as $key) {
            if (self::host($key) !== null) {
                return true;
            }
        }

        return false;
    }

    /** Déclare des routes sur l'hôte de la section, ou à la racine si aucun hôte n'est configuré. */
    public static function group(string $key, Closure $routes): void
    {
        $host = self::host($key);

        $host === null ? $routes() : Route::domain($host)->group($routes);
    }

    /** Début de déclaration de routes liées à l'hôte de la section (aucune contrainte d'hôte si elle n'en a pas). */
    public static function on(string $key): RouteRegistrar
    {
        $host = self::host($key);

        return $host === null ? Route::middleware([]) : Route::domain($host);
    }

    /** Section (site, app, store, academy) correspondant à un hôte, ou null. */
    public static function keyForHost(string $host): ?string
    {
        $host = strtolower($host);

        foreach (self::KEYS as $key) {
            if (self::host($key) === $host) {
                return $key;
            }
        }

        return null;
    }

    /** URL absolue d'un chemin sur l'hôte d'une section ; l'hôte courant si la section n'a pas d'hôte dédié. */
    public static function url(string $key, string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        $host = self::host($key);

        if ($host === null) {
            return url($path);
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.$host.($port ? ':'.$port : '').$path;
    }

    /** Section propriétaire d'un chemin de l'ancienne arborescence (/boutique, /academy, /fr…), si elle a son propre hôte. */
    public static function ownerOfPath(Request $request): ?string
    {
        $segment = explode('/', trim($request->path(), '/'))[0];

        $key = match (true) {
            in_array($segment, array_keys(config('site.locales')), true) => 'site',
            $segment === 'boutique' => 'store',
            in_array($segment, ['academy', 'certificats'], true) => 'academy',
            default => null,
        };

        return $key !== null && self::host($key) !== null ? $key : null;
    }

    /**
     * Jeu d'icônes (dossier public/app-icons/<jeu>) de l'application servie : link, store, academy ou messenger.
     * Null pour le site vitrine ou un chemin neutre (icônes de la Fondation).
     */
    public static function iconSet(Request $request): ?string
    {
        $key = self::keyForHost($request->getHost());

        if ($key === 'site') {
            return null;
        }

        if ($key !== null) {
            return $key === 'app' ? 'link' : $key;
        }

        return match (explode('/', trim($request->path(), '/'))[0]) {
            'boutique' => 'store',
            'academy', 'certificats' => 'academy',
            'messagerie' => 'messenger',
            default => null,
        };
    }

    /** Cette route de T.Link peut être servie depuis n'importe quel sous-domaine. */
    public static function isSharedRoute(?string $name): bool
    {
        return $name === null || \Illuminate\Support\Str::is((array) config('domains.shared_routes'), $name);
    }

    /**
     * Application servie par l'hôte de la requête (app, store, academy ou messenger). Null pour le site vitrine, un hôte inconnu
     * ou une installation sans sous-domaines : rien n'y est cloisonné.
     */
    public static function sectionOf(Request $request): ?string
    {
        $key = self::keyForHost($request->getHost());

        return in_array($key, self::APPS, true) ? $key : null;
    }

    /** Application propriétaire d'une route ; null pour une route technique commune (déconnexion, manifeste, appareils…). */
    public static function sectionOfRoute(?string $name): ?string
    {
        if ($name === null || $name === '' || self::isSharedRoute($name)) {
            return null;
        }

        foreach (['store', 'academy', 'messenger'] as $key) {
            if (\Illuminate\Support\Str::is($key.'.*', $name)) {
                return $key;
            }
        }

        return 'app';
    }

    /** Application propriétaire d'un chemin de page (/boutique, /academy, /messagerie…). */
    public static function sectionOfPath(string $path): string
    {
        return match (explode('/', trim($path, '/'))[0]) {
            'boutique' => 'store',
            'academy', 'certificats' => 'academy',
            'messagerie' => 'messenger',
            default => 'app',
        };
    }

    /**
     * Une page de l'application $from peut-elle renvoyer vers l'application $target ? Jamais lorsque chacune a son propre hôte :
     * Store, Academy, Messenger et T.Link sont cloisonnés. Sans hôte dédié (ou hors application), tout reste accessible.
     */
    public static function reachableFrom(?string $from, string $target): bool
    {
        return $from === null || $from === $target || self::host($target) === null;
    }

    /** Page d'accueil de l'application servie par l'hôte courant : là où un membre déjà connecté est renvoyé. */
    public static function homeUrl(Request $request): string
    {
        return match (self::sectionOf($request)) {
            'store' => route('store.index'),
            'academy' => route('academy.index'),
            'messenger' => route('messenger.index'),
            default => route('home'),
        };
    }
}
