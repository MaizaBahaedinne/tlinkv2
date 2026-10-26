<?php

namespace App\Http\Middleware;

use App\Support\LaunchGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant que le lancement n'a pas eu lieu, affiche le compte à rebours (503) à tout le monde sauf aux administrateurs globaux.
 */
class LaunchCountdown
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = LaunchGate::settings();

        if (! LaunchGate::isClosed($settings) || LaunchGate::isOpenPath($request) || LaunchGate::bypasses($request)) {
            return $next($request);
        }

        $launchAt = LaunchGate::launchAt($settings);
        $retryAfter = $launchAt ? max(60, (int) now()->diffInSeconds($launchAt, false)) : 3600;

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $settings['title'] ?: LaunchGate::DEFAULT_TITLE,
                'launch_at' => $launchAt?->toIso8601String(),
            ], 503, ['Retry-After' => $retryAfter]);
        }

        return response()->view('launch.countdown', [
            'title' => $settings['title'] ?: LaunchGate::DEFAULT_TITLE,
            'message' => $settings['message'] ?: LaunchGate::DEFAULT_MESSAGE,
            'launchAt' => $launchAt,
            'serverNow' => now()->getTimestampMs(),
        ], 503, ['Retry-After' => $retryAfter]);
    }
}
