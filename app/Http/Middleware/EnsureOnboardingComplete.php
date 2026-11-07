<?php

namespace App\Http\Middleware;

use App\Support\Onboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque, côté serveur, toute page ou route d'API réservée aux membres tant que le parcours d'intégration
 * n'est pas terminé. Seules les pages de l'étape en cours (et des étapes déjà franchies) restent accessibles.
 */
class EnsureOnboardingComplete
{
    /** Toujours accessibles : déconnexion, quitter une usurpation/simulation, hub, notifications. */
    private const ALWAYS = ['logout', 'impersonation.stop', 'simulation.stop', 'integration.*', 'notifications.*'];

    /** Routes ouvertes par étape (cumulatif : une étape franchie reste consultable). */
    private const STEP_ROUTES = [
        Onboarding::EMAIL => ['verification.*'],
        Onboarding::INFO_SESSION => ['info-session.*'],
        Onboarding::HISTORY => ['onboarding.*', 'memberships.positions', 'memberships.past', 'memberships.past.destroy'],
        Onboarding::PROFILE => ['profile.*'],
        Onboarding::STATUS => [],
        Onboarding::PLACEMENT => ['memberships.*'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if ($user === null || $route === null || ! Onboarding::applies($user) || ! in_array('auth', $route->gatherMiddleware(), true)) {
            return $next($request);
        }

        $step = Onboarding::current($user);

        if ($step === null) {
            return $next($request);
        }

        $api = $request->is('api/*');

        if ($api && $request->is('api/v1/auth/*')) {
            return $next($request);
        }

        $allowed = self::ALWAYS;

        foreach (Onboarding::unlockedSteps($user) as $unlocked) {
            $allowed = [...$allowed, ...self::STEP_ROUTES[$unlocked]];
        }

        if (! $api && $request->routeIs($allowed)) {
            return $next($request);
        }

        if ($api || $request->expectsJson()) {
            return response()->json([
                'message' => 'Terminez votre intégration pour accéder à cette fonctionnalité.',
                'onboarding_step' => $step,
            ], 403);
        }

        return redirect()->route('integration.show')
            ->with('status', 'Terminez d’abord votre intégration : cette page sera accessible une fois votre adhésion ou votre poste approuvé.');
    }
}
