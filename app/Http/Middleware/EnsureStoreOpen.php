<?php

namespace App\Http\Middleware;

use App\Support\Store\StoreSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** La boutique n'existe pour les membres qu'une fois ouverte dans les réglages (et son module actif). */
class EnsureStoreOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(StoreSettings::enabled(), 404);

        return $next($request);
    }
}
