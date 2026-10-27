<?php

use App\Http\Controllers\Site\SiteController;
use App\Http\Middleware\SetSiteLocale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site vitrine de la Fondation (charge depuis routes/web.php, a la racine)
|--------------------------------------------------------------------------
*/
$locales = implode('|', array_keys(config('site.locales')));

Route::get('/', [SiteController::class, 'root'])->name('site.root');

Route::get('medias/{attachment}', [SiteController::class, 'media'])
    ->whereNumber('attachment')
    ->name('site.media');

Route::prefix('{locale}')
    ->where(['locale' => $locales])
    ->middleware(SetSiteLocale::class)
    ->name('site.')
    ->group(function (): void {
        Route::get('/', [SiteController::class, 'home'])->name('home');
        Route::get('a-propos', [SiteController::class, 'about'])->name('about');
        Route::get('actualites', [SiteController::class, 'news'])->name('news');
        Route::get('actualites/{post}', [SiteController::class, 'newsShow'])->whereNumber('post')->name('news.show');
        Route::get('evenements', [SiteController::class, 'events'])->name('events');
        Route::get('evenements/{event}', [SiteController::class, 'eventShow'])->whereNumber('event')->name('events.show');
        // L'équipe est désormais une section de « À propos » : on garde l'ancienne adresse valide.
        Route::get('equipe', fn () => redirect(route('site.about').'#equipe', 301));
        Route::get('projets', [SiteController::class, 'projects'])->name('projects');
        Route::get('projets/{slug}', [SiteController::class, 'projectShow'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('projects.show');
        Route::get('clubs', [SiteController::class, 'clubs'])->name('clubs');
        Route::get('contact', [SiteController::class, 'contact'])->name('contact');
        Route::post('contact', [SiteController::class, 'contactStore'])
            ->middleware('throttle:5,10')
            ->name('contact.store');
        Route::post('newsletter', [SiteController::class, 'newsletterStore'])
            ->middleware('throttle:5,10')
            ->name('newsletter.store');
        Route::get('newsletter/desinscription/{token}', [SiteController::class, 'newsletterUnsubscribe'])
            ->where('token', '[A-Za-z0-9]{48}')
            ->name('newsletter.unsubscribe');
        Route::post('newsletter/desinscription/{token}', [SiteController::class, 'newsletterUnsubscribeConfirm'])
            ->where('token', '[A-Za-z0-9]{48}')
            ->middleware('throttle:10,10')
            ->name('newsletter.unsubscribe.confirm');
    });
