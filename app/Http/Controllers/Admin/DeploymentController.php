<?php

namespace App\Http\Controllers\Admin;

use App\Support\AuditLogger;
use App\Support\Deployment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * État du déploiement (administration globale) : version en ligne, migrations en attente, contrôles, vidage des caches et
 * mode maintenance. Le code, les dépendances et les migrations se déploient depuis le serveur, jamais depuis cette page.
 */
class DeploymentController extends AdminController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        $pending = Deployment::pendingMigrations();

        return view('admin.deployment.index', [
            'release' => Deployment::release(),
            'pending' => $pending,
            'checks' => Deployment::checks($pending),
            'maintenance' => Deployment::maintenance(),
            'env' => [
                'app' => config('app.name'),
                'environment' => app()->environment(),
                'url' => config('app.url'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'timezone' => config('app.timezone'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
            ],
            'latest' => \App\Models\AppRelease::query()->where('status', 'published')->orderByDesc('published_at')->orderByDesc('id')->first(),
        ]);
    }

    public function clearCaches(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        Artisan::call('optimize:clear');

        AuditLogger::record(actorId: $request->user()->id, action: 'admin.deployment.caches_cleared', targetType: 'deployment', targetId: null, metadata: [], ip: $request->ip());

        return back()->with('status', 'Caches vidés (configuration, routes, vues, application).');
    }

    public function enableMaintenance(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $secret = Str::random(32);

        Artisan::call('down', ['--secret' => $secret, '--retry' => 60]);

        AuditLogger::record(actorId: $request->user()->id, action: 'admin.deployment.maintenance_on', targetType: 'deployment', targetId: null, metadata: [], ip: $request->ip());

        // La page d'adresse secrète dépose le cookie de contournement : l'administrateur garde l'accès pendant la maintenance.
        return redirect('/'.$secret);
    }

    public function disableMaintenance(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        Artisan::call('up');

        AuditLogger::record(actorId: $request->user()->id, action: 'admin.deployment.maintenance_off', targetType: 'deployment', targetId: null, metadata: [], ip: $request->ip());

        return back()->with('status', 'Mode maintenance désactivé : le site est de nouveau en ligne.');
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
