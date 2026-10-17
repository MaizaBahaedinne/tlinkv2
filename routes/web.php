<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\NotificationPageController;
use App\Http\Controllers\PrivateMediaController;
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

Route::post('/posts/{post}/view', [FeedController::class, 'view'])
    ->name('posts.view');

Route::middleware('auth')->group(function (): void {
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/posts', [FeedController::class, 'storePost'])->name('posts.store');
    Route::post('/posts/{post}/react', [FeedController::class, 'react'])->name('posts.react');
    Route::post('/posts/{post}/comment', [FeedController::class, 'comment'])->name('posts.comment');
    Route::post('/posts/{post}/share', [FeedController::class, 'share'])->name('posts.share');
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/roadmap/action', [AdminDashboardController::class, 'updateAction'])->name('admin.roadmap.action.update');
    Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationPageController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [NotificationPageController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/media/{mediaFile}/download', [PrivateMediaController::class, 'download'])
        ->middleware('signed')
        ->name('media.download');
});
