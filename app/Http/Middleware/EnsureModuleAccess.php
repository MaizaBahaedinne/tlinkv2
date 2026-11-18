<?php

namespace App\Http\Middleware;

use App\Models\OrgNode;
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
            AdminContext::of($request)->allowsModule($item['perms'], $item['module'], $actions) || $this->clubSheetAllowed($request, $actions),
            403,
            'Les habilitations de votre poste ne couvrent pas cette action.'
        );

        return $next($request);
    }

    /**
     * La fiche d'un club (admin.postes.edit / update sur un bloc de type club) relève aussi du module « Clubs » :
     * un responsable de club gère les informations de son club sans avoir accès aux postes de l'organigramme.
     * Le périmètre (son club) est ensuite vérifié par le contrôleur.
     */
    private function clubSheetAllowed(Request $request, array $actions): bool
    {
        if (! in_array($request->route()?->getName(), ['admin.postes.edit', 'admin.postes.update'], true)) {
            return false;
        }

        $node = $request->route('node');
        $node = $node instanceof OrgNode ? $node : OrgNode::query()->find($node);

        if ($node?->kind !== 'club') {
            return false;
        }

        $item = AdminMenu::itemFor('admin.clubs.index');

        return $item !== null && AdminContext::of($request)->allowsModule($item['perms'], $item['module'], $actions);
    }
}
