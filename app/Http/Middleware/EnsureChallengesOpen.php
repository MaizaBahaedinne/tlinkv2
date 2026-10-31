<?php

namespace App\Http\Middleware;

use App\Support\Challenges\Challenges;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Les défis n'existent pour les membres que si leur module est actif et migré. */
class EnsureChallengesOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Challenges::enabled(), 404);

        return $next($request);
    }
}
