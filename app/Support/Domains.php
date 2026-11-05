<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;

/**
 * Répartition du site entre sous-domaines (site vitrine, T.Link, Store, Academy). Tout est optionnel : sans hôte configuré,
 * l'application se comporte comme un site unique.
 */
class Domains
{
    public const KEYS = ['site', 'app', 'store', 'academy'];

    /** Hôte configuré pour une section, ou null. */
    public static function host(string $key): ?string
    {
        $host = config("domains.hosts.$key");

        return is_string($host) && trim($host) !== '' ? strtolower(trim($host)) : null;
    }

    /** Au moins un sous-domaine dédié est configuré. */
    public static function enabled(): bool
    {
        foreach (['site', 'store', 'academy'] as $key) {
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

    /** Cette route de T.Link peut être servie depuis n'importe quel sous-domaine. */
    public static function isSharedRoute(?string $name): bool
    {
        return $name === null || \Illuminate\Support\Str::is((array) config('domains.shared_routes'), $name);
    }
}
