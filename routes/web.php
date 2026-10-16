<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FeedController::class, 'index'])->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [LoginController::class, 'show'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->name('login.store');

    Route::get('/inscription', [RegisterController::class, 'show'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('/deconnexion', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/posts', [FeedController::class, 'storePost'])->name('posts.store');
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
});
