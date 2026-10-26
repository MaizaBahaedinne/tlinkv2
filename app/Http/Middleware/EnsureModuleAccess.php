<?php

namespace App\Http\Middleware;

use App\Support\AdminContext;
use App\Support\AdminMenu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique la matrice CRUD du poste aux pages de l'administration : la même table (AdminMenu::MODULES)
 * commande le menu et l'accès aux routes, pour qu'ils ne divergent jamais.
 * GET = consulter, POST = créer ou modifier, PUT/PATCH = modifier, DELETE = supprimer.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->route()?->getName() === 'admin.dashboard') {
            abort_unless(AdminMenu::hasAccess(AdminContext::of($request)), 403, 'Les habilitations de votre poste ne donnent accès à aucun module de l’administration.');
        }

        $item = AdminMenu::itemFor($request->route()?->getName());

        if ($item === null || ! $request->user()) {
            return $next($request);
        }

        $actions = match ($request->method()) {
            'POST' => ['create', 'update'],
            'PUT', 'PATCH' => ['update'],
            'DELETE' => ['delete'],
            default => ['read'],
        };

        abort_unless(
            AdminContext::of($request)->allowsModule($item['perms'], $item['module'], $actions),
            403,
            'Les habilitations de votre poste ne couvrent pas cette action.'
        );

        return $next($request);
    }
}
