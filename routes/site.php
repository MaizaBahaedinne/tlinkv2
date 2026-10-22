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
        Route::get('equipe', [SiteController::class, 'team'])->name('team');
        Route::get('clubs', [SiteController::class, 'clubs'])->name('clubs');
        Route::get('contact', [SiteController::class, 'contact'])->name('contact');
        Route::post('contact', [SiteController::class, 'contactStore'])
            ->middleware('throttle:5,10')
            ->name('contact.store');
    });
