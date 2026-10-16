<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);

    Route::get('/posts', [FeedController::class, 'index']);
    Route::get('/entities', [OrganizationController::class, 'entities']);
    Route::get('/governorates', [OrganizationController::class, 'governorates']);

    Route::middleware('auth')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/posts', [FeedController::class, 'store']);
    });
});
