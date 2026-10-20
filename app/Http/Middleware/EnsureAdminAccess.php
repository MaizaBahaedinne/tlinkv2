<?php

namespace App\Http\Middleware;

use App\Support\AdminContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('admin.can:entity.manage,membership.manage')
 * L'acces est accorde si l'utilisateur possede AU MOINS UNE des permissions listees.
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        abort_unless($request->user(), 403, 'Acces refuse.');

        $context = AdminContext::of($request);

        abort_unless(
            $context->canAny($permissions !== [] ? $permissions : ['entity.manage']),
            403,
            'Vous n\'avez pas les droits necessaires pour acceder a cette section.'
        );

        return $next($request);
    }
}
