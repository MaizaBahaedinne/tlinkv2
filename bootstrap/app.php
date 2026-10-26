<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\LaunchCountdown;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        // Compte a rebours de lancement : ferme le site (web + API) aux non-administrateurs globaux jusqu'a la date choisie.
        $middleware->web(append: [LaunchCountdown::class]);
        $middleware->api(append: [LaunchCountdown::class]);
        // Un utilisateur deja connecte qui ouvre /connexion ou /inscription arrive sur le fil, pas sur la vitrine.
        $middleware->redirectUsersTo(fn () => route('home'));
        $middleware->alias([
            'admin.can' => EnsureAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
