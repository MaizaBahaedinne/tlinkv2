<?php

namespace App\Http\Middleware;

use App\Support\Domains;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sous-domaines : une page de T.Link demandée depuis le site vitrine, le Store ou l'Academy (ancien lien, URL tapée à la main)
 * est redirigée vers l'hôte T.Link. Les routes partagées (messagerie, notifications, recherche…) restent servies sur place.
 */
class RedirectToAppHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $appHost = Domains::host('app');
        $route = $request->route();

        if ($appHost === null || ! Domains::enabled() || $route === null || $route->isFallback || $route->getDomain() !== null) {
            return $next($request);
        }

        $host = strtolower($request->getHost());

        if ($host === $appHost || Domains::keyForHost($host) === null || Domains::isSharedRoute($route->getName())) {
            return $next($request);
        }

        // 308 conserve la méthode et le corps des requêtes non GET.
        return redirect()->to(Domains::url('app', $request->getRequestUri()), $request->isMethodSafe() ? 301 : 308);
    }
}
