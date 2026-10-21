<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique la langue du site public (segment d'URL {locale}) et la memorise pour la generation des liens.
 */
class SetSiteLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');

        abort_unless(array_key_exists($locale, config('site.locales')), 404);

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->route()->forgetParameter('locale');

        return $next($request);
    }
}
