<?php

namespace App\Http\Middleware;

use App\Models\OrgNode;
use App\Support\RoleSimulator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique la simulation de rôle enregistrée en session : tant qu'elle est valide (même compte, poste toujours simulable,
 * droits d'administrateur global toujours présents), les droits de l'utilisateur sont ceux du poste simulé.
 * Toute simulation devenue invalide est abandonnée silencieusement.
 */
class ApplyRoleSimulation
{
    public function handle(Request $request, Closure $next): Response
    {
        RoleSimulator::deactivate();

        $user = $request->user();

        if ($user && $request->hasSession() && $request->session()->has(RoleSimulator::SESSION_KEY)) {
            $state = (array) $request->session()->get(RoleSimulator::SESSION_KEY);
            $node = (int) ($state['user_id'] ?? 0) === $user->id
                ? OrgNode::query()->find((int) ($state['node_id'] ?? 0))
                : null;

            if ($node !== null && RoleSimulator::isSimulable($node) && RoleSimulator::canUse($user)) {
                RoleSimulator::activate($user, $node);
            } else {
                $request->session()->forget(RoleSimulator::SESSION_KEY);
            }
        }

        return $next($request);
    }

    /** La simulation ne survit pas à la requête (processus longs, tests). */
    public function terminate(Request $request, Response $response): void
    {
        RoleSimulator::deactivate();
    }
}
