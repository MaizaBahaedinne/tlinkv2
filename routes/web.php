<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\NotificationPageController;
use App\Http\Controllers\OrgChartController;
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

Route::get('/posts/attachments/{attachment}', [FeedController::class, 'showAttachment'])
    ->name('posts.attachments.show');

Route::middleware('auth')->group(function (): void {
    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/posts', [FeedController::class, 'storePost'])->name('posts.store');
    Route::patch('/posts/{post}', [FeedController::class, 'updatePost'])->name('posts.update');
    Route::delete('/posts/{post}', [FeedController::class, 'destroyPost'])->name('posts.destroy');
    Route::patch('/posts/{post}/comments-state', [FeedController::class, 'toggleComments'])->name('posts.comments-state');
    Route::post('/posts/{post}/react', [FeedController::class, 'react'])->name('posts.react');
    Route::post('/posts/{post}/comment', [FeedController::class, 'comment'])->name('posts.comment');
    Route::post('/posts/{post}/share', [FeedController::class, 'share'])->name('posts.share');
    Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationPageController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [NotificationPageController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/media/{mediaFile}/download', [PrivateMediaController::class, 'download'])
        ->middleware('signed')
        ->name('media.download');
});

/*
|--------------------------------------------------------------------------
| Organigramme de la Fondation
|--------------------------------------------------------------------------
| Lecture pour tout utilisateur connecte ; l'ecriture exige `entity.manage`
| en perimetre global (verifie dans le controleur).
*/
Route::middleware('auth')->prefix('organigramme')->name('orgchart.')->group(function (): void {
    Route::get('/', [OrgChartController::class, 'index'])->name('index');
    Route::get('donnees', [OrgChartController::class, 'data'])->name('data');
    Route::get('historique', [OrgChartController::class, 'history'])->name('history');
    Route::get('export.json', [OrgChartController::class, 'exportJson'])->name('export.json');
    Route::get('export.csv', [OrgChartController::class, 'exportCsv'])->name('export.csv');
    Route::post('synchroniser', [OrgChartController::class, 'sync'])->name('sync');

    Route::post('blocs', [OrgChartController::class, 'storeNode'])->name('nodes.store');
    Route::patch('blocs/{node}', [OrgChartController::class, 'updateNode'])->name('nodes.update');
    Route::delete('blocs/{node}', [OrgChartController::class, 'destroyNode'])->name('nodes.destroy');
    Route::post('blocs/{node}/ordre', [OrgChartController::class, 'reorder'])->name('nodes.reorder');
    Route::put('blocs/{node}/affectations', [OrgChartController::class, 'syncAssignments'])->name('nodes.assignments');
    Route::post('blocs/{node}/modele', [OrgChartController::class, 'applyTemplate'])->name('nodes.template');
    Route::post('disposition', [OrgChartController::class, 'saveLayout'])->name('layout.save');
    Route::post('clubs', [OrgChartController::class, 'storeClub'])->name('clubs.store');

    Route::get('utilisateurs', [OrgChartController::class, 'searchUsers'])->name('users.search');
    Route::post('blocs/{node}/utilisateurs', [OrgChartController::class, 'assignUser'])->name('nodes.users');
    Route::post('personnes', [OrgChartController::class, 'storePerson'])->name('people.store');
    Route::patch('personnes/{person}', [OrgChartController::class, 'updatePerson'])->name('people.update');
    Route::delete('personnes/{person}', [OrgChartController::class, 'destroyPerson'])->name('people.destroy');

    Route::post('import', [OrgChartController::class, 'import'])->name('import');
});

/*
|--------------------------------------------------------------------------
| Espace d'administration
|--------------------------------------------------------------------------
| Chaque section est protegee par `admin.can:<permissions>` (au moins une).
| Le perimetre (global ou limite a des perimetres) est ensuite applique dans les controleurs.
*/
Route::middleware(['auth', 'admin.can:entity.manage,membership.manage,approval.review,post.moderate,event.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        // Organisation + pilotage (entity.manage)
        Route::middleware('admin.can:entity.manage')->group(function (): void {
            Route::get('feuille-de-route', [Admin\RoadmapController::class, 'index'])->name('roadmap.index');
            Route::post('feuille-de-route', [Admin\RoadmapController::class, 'update'])->name('roadmap.update');
            Route::get('journal', [Admin\AuditLogController::class, 'index'])->name('audit.index');
            Route::get('utilisateurs/{user}/modifier', [Admin\UserController::class, 'edit'])->name('users.edit');
            Route::put('utilisateurs/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        });

        // Communaute
        Route::middleware('admin.can:entity.manage,membership.manage')->group(function (): void {
            Route::get('utilisateurs', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('utilisateurs/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        });

        Route::middleware('admin.can:membership.manage')->group(function (): void {
            Route::get('adhesions', [Admin\MembershipController::class, 'index'])->name('memberships.index');
            Route::patch('adhesions/{membership}', [Admin\MembershipController::class, 'update'])->name('memberships.update');
        });

        // Contenu
        Route::middleware('admin.can:post.moderate')->group(function (): void {
            Route::get('publications', [Admin\PostController::class, 'index'])->name('posts.index');
            Route::patch('publications/{post}/commentaires', [Admin\PostController::class, 'toggleComments'])->name('posts.comments');
            Route::delete('publications/{post}', [Admin\PostController::class, 'destroy'])->name('posts.destroy');
            Route::get('moderation', [Admin\ModerationController::class, 'index'])->name('moderation.index');
            Route::patch('moderation/{report}', [Admin\ModerationController::class, 'update'])->name('moderation.update');
        });

        // Gouvernance
        Route::middleware('admin.can:approval.review')->group(function (): void {
            Route::get('approbations', [Admin\ApprovalController::class, 'index'])->name('approvals.index');
            Route::post('approbations/{approvalRequest}', [Admin\ApprovalController::class, 'decide'])->name('approvals.decide');
        });

        Route::middleware('admin.can:event.manage')->group(function (): void {
            Route::get('evenements', [Admin\EventController::class, 'index'])->name('events.index');
            Route::get('evenements/creer', [Admin\EventController::class, 'create'])->name('events.create');
            Route::post('evenements', [Admin\EventController::class, 'store'])->name('events.store');
            Route::get('evenements/{event}', [Admin\EventController::class, 'show'])->name('events.show');
            Route::get('evenements/{event}/modifier', [Admin\EventController::class, 'edit'])->name('events.edit');
            Route::put('evenements/{event}', [Admin\EventController::class, 'update'])->name('events.update');
            Route::delete('evenements/{event}', [Admin\EventController::class, 'destroy'])->name('events.destroy');
            Route::patch('evenements/{event}/inscriptions/{registration}', [Admin\EventController::class, 'updateRegistration'])->name('events.registrations.update');
        });
    });
