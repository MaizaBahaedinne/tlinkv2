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
        View::composer(['app', 'profile.show'], function ($view): void {
            $request = request();

            $view->with('canAdmin', $request->user() !== null && AdminMenu::hasAccess(AdminContext::of($request)));
        });
    }
}
