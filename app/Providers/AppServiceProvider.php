<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use App\Support\AdminContext;
use App\Support\AdminMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Badges : le recalcul se déclenche à l'enregistrement des modèles listés dans le catalogue d'activités.
        \App\Support\Gamification\ActivityCatalog::listen();

        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perMinute(120)->by($key),
            ];
        });

        View::composer('admin.layouts.app', function ($view): void {
            $request = request();

            if ($request->user()) {
                $context = AdminContext::of($request);
                $view->with('adminUser', $request->user());
                $view->with('adminNav', AdminMenu::build($context));
            }
        });

        // Les liens « Administration » de l'application suivent les mêmes droits que le menu admin.
        View::composer(['app', 'profile.show', 'partials.app-header'], function ($view): void {
            $request = request();

            $view->with('canAdmin', $request->user() !== null && AdminMenu::hasAccess(AdminContext::of($request)));
            $view->with('hasOffice', $request->user() !== null && \App\Support\MyOffice::enabled() && (\App\Support\MyOffice::holdsPosition($request->user()) || \App\Support\MyOfficeSpace::hasActivity($request->user())));
        });

        // Pastille de notifications non lues de la barre de navigation T.Link.
        View::composer('partials.app-header', function ($view): void {
            $user = request()->user();

            $view->with('unreadNotifications', $user === null ? 0 : \App\Models\AppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count());

            // T.Link Messenger : lien et pastille des messages non lus (uniquement si la messagerie est ouverte).
            $messengerOpen = $user !== null && \App\Support\Messenger\Messenger::enabled();
            $view->with('messengerEnabled', $messengerOpen);
            $view->with('unreadMessages', $messengerOpen ? \App\Support\Messenger\Messenger::unreadCount($user) : 0);
        });
    }
}
