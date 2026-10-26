<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\InfoSessionController;
use App\Http\Controllers\NotificationPageController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\PositionJoinController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Site vitrine de la Fondation : racine du site (/ puis /fr, /ar, /en).
require base_path('routes/site.php');

// Lien d'inscription direct d'un poste de l'organigramme (public : jeton non devinable, candidature a valider).
Route::get('/rejoindre/{token}', [PositionJoinController::class, 'show'])->where('token', '[A-Za-z0-9]{40}')->name('position.join');
Route::post('/rejoindre/{token}', [PositionJoinController::class, 'store'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:10,10')->name('position.join.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [LoginController::class, 'show'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->name('login.store');

    Route::get('/inscription', [RegisterController::class, 'show'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'store'])->name('register.store');

    // Connexion / inscription via un reseau social (Google, Facebook, LinkedIn).
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

Route::post('/deconnexion', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/impersonation/quitter', [Admin\ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonation.stop');

Route::get('/posts/attachments/{attachment}', [FeedController::class, 'showAttachment'])
    ->name('posts.attachments.show');

Route::middleware('auth')->group(function (): void {
    // Fil d'actualite de l'application T.Link (reserve aux membres connectes).
    Route::get('/feed', [FeedController::class, 'index'])->name('home');
    Route::post('/posts/{post}/view', [FeedController::class, 'view'])->name('posts.view');

    Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');

    // Info session (video) puis quiz : reussir le quiz valide le compte.
    Route::get('/info-session', [InfoSessionController::class, 'show'])->name('info-session.show');
    Route::post('/info-session/quiz', [InfoSessionController::class, 'start'])->middleware('throttle:8,10')->name('info-session.start');
    Route::get('/info-session/quiz/{attempt}', [InfoSessionController::class, 'quiz'])->name('info-session.quiz');
    Route::post('/info-session/quiz/{attempt}', [InfoSessionController::class, 'submit'])->middleware('throttle:20,10')->name('info-session.submit');
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
    Route::post('blocs/{node}/lien-inscription', [OrgChartController::class, 'regenerateJoinLink'])->name('nodes.join-link');
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
            Route::get('mandats', [Admin\MandateController::class, 'index'])->name('mandates.index');
            Route::get('lancement', [Admin\LaunchController::class, 'edit'])->name('launch.edit');
            Route::get('modules', [Admin\AppModuleController::class, 'index'])->name('modules.index');
            Route::post('modules', [Admin\AppModuleController::class, 'store'])->name('modules.store');
            Route::post('modules/completer', [Admin\AppModuleController::class, 'sync'])->name('modules.sync');
            Route::put('modules/{module}', [Admin\AppModuleController::class, 'update'])->name('modules.update');
            Route::delete('modules/{module}', [Admin\AppModuleController::class, 'destroy'])->name('modules.destroy');
            Route::put('lancement', [Admin\LaunchController::class, 'update'])->name('launch.update');
            Route::post('mandats', [Admin\MandateController::class, 'store'])->name('mandates.store');
            Route::put('mandats/{mandate}', [Admin\MandateController::class, 'update'])->name('mandates.update');
            Route::delete('mandats/{mandate}', [Admin\MandateController::class, 'destroy'])->name('mandates.destroy');
            Route::get('journal', [Admin\AuditLogController::class, 'index'])->name('audit.index');
            Route::get('utilisateurs/{user}/modifier', [Admin\UserController::class, 'edit'])->name('users.edit');
            Route::put('utilisateurs/{user}', [Admin\UserController::class, 'update'])->name('users.update');
            Route::post('utilisateurs/{user}/se-connecter-en-tant-que', [Admin\ImpersonationController::class, 'start'])->name('users.impersonate');

            // Site public de la Fondation
            Route::get('site-public', [Admin\SiteSettingsController::class, 'edit'])->name('site.edit');
            Route::put('site-public', [Admin\SiteSettingsController::class, 'update'])->name('site.update');
            Route::get('partenaires', [Admin\PartnerController::class, 'index'])->name('partners.index');
            Route::post('partenaires', [Admin\PartnerController::class, 'store'])->name('partners.store');
            Route::put('partenaires/{partner}', [Admin\PartnerController::class, 'update'])->name('partners.update');
            Route::delete('partenaires/{partner}', [Admin\PartnerController::class, 'destroy'])->name('partners.destroy');
            Route::get('info-session', [Admin\InfoSessionController::class, 'edit'])->name('info-session.edit');
            Route::put('info-session', [Admin\InfoSessionController::class, 'update'])->name('info-session.update');
            Route::get('messages', [Admin\ContactMessageController::class, 'index'])->name('messages.index');
            Route::patch('messages/{message}', [Admin\ContactMessageController::class, 'update'])->name('messages.update');
            Route::delete('messages/{message}', [Admin\ContactMessageController::class, 'destroy'])->name('messages.destroy');

            // Structure : tableaux et fiches (postes, droits, titulaires, clubs)
            Route::get('structure/postes', [Admin\StructureController::class, 'index'])->name('postes.index');
            Route::post('structure/postes', [Admin\StructureController::class, 'store'])->name('postes.store');
            Route::post('structure/postes/droits', [Admin\StructureController::class, 'savePermissions'])->name('postes.permissions');
            Route::get('structure/postes/{node}/modifier', [Admin\StructureController::class, 'edit'])->name('postes.edit');
            Route::put('structure/postes/{node}', [Admin\StructureController::class, 'update'])->name('postes.update');
            Route::put('structure/postes/{node}/habilitations', [Admin\StructureController::class, 'saveModulePermissions'])->name('postes.modules');
            Route::delete('structure/postes/{node}', [Admin\StructureController::class, 'destroy'])->name('postes.destroy');
            Route::post('structure/postes/{node}/modele', [Admin\StructureController::class, 'applyTemplate'])->name('postes.template');
            Route::post('structure/postes/{node}/titulaires', [Admin\StructureController::class, 'assign'])->name('postes.assign');
            Route::delete('structure/postes/{node}/titulaires/{person}', [Admin\StructureController::class, 'unassign'])->name('postes.unassign');
            Route::get('structure/candidatures', [Admin\PositionApplicationController::class, 'index'])->name('candidatures.index');
            Route::post('structure/candidatures/{application}', [Admin\PositionApplicationController::class, 'decide'])->name('candidatures.decide');
            Route::get('structure/clubs', [Admin\StructureController::class, 'clubs'])->name('clubs.index');
            Route::post('structure/clubs', [Admin\StructureController::class, 'storeClub'])->name('clubs.store');
            Route::post('structure/clubs/aligner', [Admin\StructureController::class, 'alignClubs'])->name('clubs.align');
            Route::post('structure/clubs/statut', [Admin\StructureController::class, 'bulkClubStatus'])->name('clubs.status');
            Route::get('structure/clubs/{node}', [Admin\StructureController::class, 'showClub'])->name('clubs.show');
            Route::get('structure/roles', [Admin\StructureController::class, 'roles'])->name('roles.index');
            Route::put('structure/roles', [Admin\StructureController::class, 'updateRoles'])->name('roles.update');
            Route::get('structure/perimetres', [Admin\ScopeSettingsController::class, 'edit'])->name('scopes.edit');
            Route::put('structure/perimetres', [Admin\ScopeSettingsController::class, 'update'])->name('scopes.update');
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
            Route::patch('publications/{post}/vitrine', [Admin\PostController::class, 'toggleSiteNews'])->name('posts.site-news');
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
