<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminExportController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\ModerationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);

    Route::get('/posts', [FeedController::class, 'index']);
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/entities', [OrganizationController::class, 'entities']);
    Route::get('/clubs', [OrganizationController::class, 'clubs']);
    Route::get('/governorates', [OrganizationController::class, 'governorates']);

    Route::middleware('auth')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::post('/posts', [FeedController::class, 'store']);
        Route::post('/posts/{post}/react', [FeedController::class, 'react']);
        Route::post('/posts/{post}/comment', [FeedController::class, 'comment']);
        Route::post('/posts/{post}/share', [FeedController::class, 'share']);
        Route::post('/posts/{post}/view', [FeedController::class, 'view']);
        Route::post('/moderation/reports', [ModerationController::class, 'report']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/memberships', [MembershipController::class, 'index']);
        Route::post('/memberships', [MembershipController::class, 'store']);

        Route::get('/approvals', [ApprovalController::class, 'index']);
        Route::post('/approvals/{approvalRequest}/decision', [ApprovalController::class, 'decide']);

        Route::post('/events', [EventController::class, 'store']);
        Route::post('/events/{event}/register', [EventController::class, 'register']);

        Route::prefix('/admin')->group(function (): void {
            Route::get('/overview', [AdminController::class, 'overview']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/audit-logs', [AdminController::class, 'auditLogs']);

            Route::get('/exports/users', [AdminExportController::class, 'users']);
            Route::get('/exports/memberships', [AdminExportController::class, 'memberships']);
            Route::get('/exports/events', [AdminExportController::class, 'events']);
            Route::get('/exports/event-registrations', [AdminExportController::class, 'eventRegistrations']);
            Route::get('/exports/moderation-reports', [AdminExportController::class, 'moderationReports']);

            Route::get('/moderation-reports', [ModerationController::class, 'index']);
            Route::post('/moderation-reports/{moderationReport}/review', [ModerationController::class, 'review']);
        });
    });
});
