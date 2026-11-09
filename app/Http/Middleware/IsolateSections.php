<?php

namespace App\Http\Middleware;

use App\Support\Domains;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloisonnement des applications : T.Link, Store, Academy et Messenger ont chacune leur sous-domaine et ne s'interconnectent pas.
 * Une page d'une autre application demandée depuis l'hôte courant est introuvable (404). Seules les routes techniques communes
 * (médias, appareils, manifeste, déconnexion…) et la connexion (qui reste sur l'hôte T.Link, pour Google) font exception.
 * Depuis le site vitrine, une ancienne adresse est redirigée vers l'hôte de son application.
 */
class IsolateSections
{
    /** Routes de connexion : elles ne vivent que sur l'hôte T.Link (retour Google), on y renvoie les autres hôtes. */
    private const SIGN_IN = ['login', 'login.store', 'register', 'register.store', 'social.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if (! Domains::enabled() || $route === null || $route->isFallback || $route->getDomain() !== null) {
            return $next($request);
        }

        $key = Domains::keyForHost($request->getHost());
        $owner = Domains::sectionOfRoute($route->getName());

        if ($key === null || $owner === null || $key === $owner) {
            return $next($request);
        }

        if ($key === 'site') {
            $targetHost = Domains::host($owner);

            return $targetHost === null ? $next($request) : $this->redirectTo($request, $owner);
        }

        if (Domains::host($owner) === null) {
            return $next($request);
        }

        if ($owner === 'app' && \Illuminate\Support\Str::is(self::SIGN_IN, (string) $route->getName())) {
            return $this->redirectTo($request, 'app');
        }

        abort(404);
    }

    private function redirectTo(Request $request, string $section): Response
    {
        // 308 conserve la méthode et le corps des requêtes non GET.
        return redirect()->to(Domains::url($section, $request->getRequestUri()), $request->isMethodSafe() ? 301 : 308);
    }
}
