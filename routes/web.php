<?php

use App\Http\Controllers\AcademyController;
use App\Http\Controllers\AcademyQuizController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\InfoSessionController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\MentoringController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\MyOfficeController;
use App\Http\Controllers\NotificationPageController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\PositionJoinController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Site vitrine de la Fondation : racine du site (/ puis /fr, /ar, /en).
require base_path('routes/site.php');

// Certificat Academy : page publique, vérifiable grâce à son code.
Route::get('/certificats/{code}', [AcademyController::class, 'certificate'])->where('code', 'TA-[A-Z0-9]{10}')->middleware('throttle:certificate-check')->name('academy.certificate');

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
    Route::get('/badges', [BadgeController::class, 'index'])->name('badges.index');

    // Tunivisions Academy : catalogue, cours, leçons, commandes et calendrier.
    Route::prefix('academy')->name('academy.')->group(function (): void {
        Route::get('/', [AcademyController::class, 'index'])->name('index');
        Route::get('/calendrier', [AcademyController::class, 'calendar'])->name('calendar');
        Route::get('/cours/{course:slug}', [AcademyController::class, 'show'])->name('courses.show');
        Route::post('/cours/{course:slug}/commencer', [AcademyController::class, 'start'])->name('courses.start');
        Route::post('/cours/{course:slug}/acheter', [AcademyController::class, 'buyCourse'])->middleware('throttle:academy-action')->name('courses.buy');
        Route::post('/cours/{course:slug}/avis', [AcademyController::class, 'review'])->middleware('throttle:academy-action')->name('courses.review');
        Route::get('/lecons/{lesson}', [AcademyController::class, 'lesson'])->whereNumber('lesson')->name('lessons.show');
        Route::post('/lecons/{lesson}/terminer', [AcademyController::class, 'complete'])->whereNumber('lesson')->name('lessons.complete');
        Route::post('/abonnements/{plan}/souscrire', [AcademyController::class, 'subscribe'])->whereNumber('plan')->middleware('throttle:academy-action')->name('plans.subscribe');
        Route::get('/commandes/{order}', [AcademyController::class, 'order'])->whereNumber('order')->name('orders.show');
        Route::get('/quiz/{quiz}', [AcademyQuizController::class, 'show'])->whereNumber('quiz')->name('quizzes.show');
        Route::post('/quiz/{quiz}/commencer', [AcademyQuizController::class, 'start'])->whereNumber('quiz')->middleware('throttle:academy-quiz')->name('quizzes.start');
        Route::get('/quiz/tentatives/{attempt}', [AcademyQuizController::class, 'attempt'])->whereNumber('attempt')->name('attempts.show');
        Route::post('/quiz/tentatives/{attempt}', [AcademyQuizController::class, 'submit'])->whereNumber('attempt')->middleware('throttle:academy-quiz')->name('attempts.submit');
    });
    Route::get('/membres/{user}', [MemberProfileController::class, 'show'])->whereNumber('user')->name('member.show');

    // T.Link Messenger : messagerie instantanée (pages + JSON interrogé par le navigateur).
    Route::prefix('messagerie')->name('messenger.')->group(function (): void {
        Route::get('/', [MessengerController::class, 'index'])->name('index');
        Route::get('/conversations', [MessengerController::class, 'conversations'])->middleware('throttle:messenger-poll')->name('conversations');
        Route::get('/non-lus', [MessengerController::class, 'unread'])->middleware('throttle:messenger-poll')->name('unread');
        Route::get('/membres', [MessengerController::class, 'members'])->middleware('throttle:messenger-action')->name('members');
        Route::post('/directe/{user}', [MessengerController::class, 'direct'])->whereNumber('user')->middleware('throttle:messenger-start')->name('direct');
        Route::post('/groupes', [MessengerController::class, 'storeGroup'])->middleware('throttle:messenger-start')->name('groups.store');
        Route::post('/bloquer/{user}', [MessengerController::class, 'block'])->whereNumber('user')->name('block');
        Route::delete('/bloquer/{user}', [MessengerController::class, 'unblock'])->whereNumber('user')->name('unblock');
        Route::delete('/messages/{message}', [MessengerController::class, 'destroyMessage'])->whereNumber('message')->name('messages.destroy');
        Route::get('/messages/{message}/image', [MessengerController::class, 'image'])->whereNumber('message')->name('image');
        Route::get('/{conversation}', [MessengerController::class, 'index'])->whereNumber('conversation')->name('show');
        Route::get('/{conversation}/messages', [MessengerController::class, 'messages'])->whereNumber('conversation')->middleware('throttle:messenger-poll')->name('messages');
        Route::post('/{conversation}/messages', [MessengerController::class, 'send'])->whereNumber('conversation')->middleware('throttle:messenger-action')->name('send');
        Route::post('/{conversation}/lu', [MessengerController::class, 'read'])->whereNumber('conversation')->middleware('throttle:messenger-action')->name('read');
        Route::post('/{conversation}/ecriture', [MessengerController::class, 'typing'])->whereNumber('conversation')->middleware('throttle:messenger-action')->name('typing');
        Route::post('/{conversation}/quitter', [MessengerController::class, 'leave'])->whereNumber('conversation')->name('leave');
    });

    // Mentorat : les membres remontent demandes et incidents, le manager mentor qualifie et affecte, le mentor accompagne et rédige le REX.
    Route::prefix('mentorat')->name('mentoring.')->group(function (): void {
        Route::get('/', [MentoringController::class, 'index'])->name('index');
        Route::get('/nouvelle', [MentoringController::class, 'create'])->name('create');
        Route::post('/', [MentoringController::class, 'store'])->middleware('throttle:mentoring-submit')->name('store');
        Route::get('/accompagnements', [MentoringController::class, 'assigned'])->name('assigned');
        // Anciennes adresses de la gestion : elle se fait désormais dans l'administration.
        Route::redirect('/gestion', '/admin/mentorat');
        Route::redirect('/mentors', '/admin/mentorat/mentors');
        Route::get('/{ticket}', [MentoringController::class, 'show'])->whereNumber('ticket')->name('show');
        Route::middleware('throttle:mentoring-action')->group(function (): void {
            Route::post('/{ticket}/commentaires', [MentoringController::class, 'comment'])->whereNumber('ticket')->name('comment');
            Route::post('/{ticket}/qualification', [MentoringController::class, 'qualify'])->whereNumber('ticket')->name('qualify');
            Route::post('/{ticket}/rejet', [MentoringController::class, 'reject'])->whereNumber('ticket')->name('reject');
            Route::post('/{ticket}/affectation', [MentoringController::class, 'assign'])->whereNumber('ticket')->name('assign');
            Route::post('/{ticket}/demarrer', [MentoringController::class, 'start'])->whereNumber('ticket')->name('start');
            Route::put('/{ticket}/rex', [MentoringController::class, 'rex'])->whereNumber('ticket')->name('rex');
            Route::post('/{ticket}/resolution', [MentoringController::class, 'resolve'])->whereNumber('ticket')->name('resolve');
            Route::post('/{ticket}/cloture', [MentoringController::class, 'close'])->whereNumber('ticket')->name('close');
            Route::post('/{ticket}/reouverture', [MentoringController::class, 'reopen'])->whereNumber('ticket')->name('reopen');
        });
    });

    // Recherche de la barre de navigation : page de résultats + suggestions (JSON).
    Route::get('/recherche', [SearchController::class, 'index'])->name('search.index');
    Route::get('/recherche/suggestions', [SearchController::class, 'suggest'])->middleware('throttle:search-suggest')->name('search.suggest');

    // Mon bureau : supérieurs, équipe, et gestion des titulaires des postes situés sous les siens.
    Route::get('/mon-bureau', [MyOfficeController::class, 'index'])->name('office.index');
    Route::post('/mon-bureau/postes/{node}/titulaires', [MyOfficeController::class, 'assign'])->middleware('throttle:office-assign')->name('office.assign');
    Route::delete('/mon-bureau/postes/{node}/titulaires/{person}', [MyOfficeController::class, 'unassign'])->name('office.unassign');
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
Route::middleware(['auth', 'admin.can:entity.manage,membership.manage,approval.review,post.moderate,event.manage,mentoring.manage,mentoring.roster', 'admin.module'])
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
            Route::get('badges', [Admin\BadgeController::class, 'index'])->name('badges.index');
            Route::post('badges', [Admin\BadgeController::class, 'store'])->name('badges.store');
            Route::post('badges/recalculer', [Admin\BadgeController::class, 'sync'])->name('badges.sync');
            Route::get('badges/{badge}/modifier', [Admin\BadgeController::class, 'edit'])->name('badges.edit');
            Route::put('badges/{badge}', [Admin\BadgeController::class, 'update'])->name('badges.update');
            Route::delete('badges/{badge}', [Admin\BadgeController::class, 'destroy'])->name('badges.destroy');
            Route::post('badges/{badge}/missions', [Admin\BadgeController::class, 'storeMission'])->name('badges.missions.store');
            Route::put('badges/missions/{mission}', [Admin\BadgeController::class, 'updateMission'])->name('badges.missions.update');
            Route::delete('badges/missions/{mission}', [Admin\BadgeController::class, 'destroyMission'])->name('badges.missions.destroy');
            Route::post('badges/missions/{mission}/points', [Admin\BadgeController::class, 'storeCheckpoint'])->name('badges.checkpoints.store');
            Route::put('badges/points/{checkpoint}', [Admin\BadgeController::class, 'updateCheckpoint'])->name('badges.checkpoints.update');
            Route::delete('badges/points/{checkpoint}', [Admin\BadgeController::class, 'destroyCheckpoint'])->name('badges.checkpoints.destroy');
            Route::get('etablissements', [Admin\InstitutionController::class, 'index'])->name('institutions.index');
            Route::post('etablissements', [Admin\InstitutionController::class, 'store'])->name('institutions.store');

            // Tunivisions Academy : cours, commandes, abonnements et réglages
            Route::get('academy/cours', [Admin\AcademyCourseController::class, 'index'])->name('academy.courses.index');
            Route::get('academy/cours/creer', [Admin\AcademyCourseController::class, 'create'])->name('academy.courses.create');
            Route::post('academy/cours', [Admin\AcademyCourseController::class, 'store'])->name('academy.courses.store');
            Route::get('academy/cours/{course}/modifier', [Admin\AcademyCourseController::class, 'edit'])->whereNumber('course')->name('academy.courses.edit');
            Route::put('academy/cours/{course}', [Admin\AcademyCourseController::class, 'update'])->whereNumber('course')->name('academy.courses.update');
            Route::delete('academy/cours/{course}', [Admin\AcademyCourseController::class, 'destroy'])->whereNumber('course')->name('academy.courses.destroy');
            Route::post('academy/cours/{course}/modules', [Admin\AcademyCourseController::class, 'storeModule'])->whereNumber('course')->name('academy.courses.modules.store');
            Route::put('academy/modules/{module}', [Admin\AcademyCourseController::class, 'updateModule'])->whereNumber('module')->name('academy.courses.modules.update');
            Route::delete('academy/modules/{module}', [Admin\AcademyCourseController::class, 'destroyModule'])->whereNumber('module')->name('academy.courses.modules.destroy');
            Route::post('academy/modules/{module}/lecons', [Admin\AcademyCourseController::class, 'storeLesson'])->whereNumber('module')->name('academy.courses.lessons.store');
            Route::put('academy/lecons/{lesson}', [Admin\AcademyCourseController::class, 'updateLesson'])->whereNumber('lesson')->name('academy.courses.lessons.update');
            Route::delete('academy/lecons/{lesson}', [Admin\AcademyCourseController::class, 'destroyLesson'])->whereNumber('lesson')->name('academy.courses.lessons.destroy');
            Route::post('academy/cours/{course}/quiz', [Admin\AcademyQuizController::class, 'store'])->whereNumber('course')->name('academy.courses.quiz.store');
            Route::get('academy/quiz/{quiz}/modifier', [Admin\AcademyQuizController::class, 'edit'])->whereNumber('quiz')->name('academy.courses.quiz.edit');
            Route::put('academy/quiz/{quiz}', [Admin\AcademyQuizController::class, 'update'])->whereNumber('quiz')->name('academy.courses.quiz.update');
            Route::delete('academy/quiz/{quiz}', [Admin\AcademyQuizController::class, 'destroy'])->whereNumber('quiz')->name('academy.courses.quiz.destroy');
            Route::post('academy/quiz/{quiz}/questions', [Admin\AcademyQuizController::class, 'storeQuestion'])->whereNumber('quiz')->name('academy.courses.quiz.questions.store');
            Route::put('academy/quiz/questions/{question}', [Admin\AcademyQuizController::class, 'updateQuestion'])->whereNumber('question')->name('academy.courses.quiz.questions.update');
            Route::delete('academy/quiz/questions/{question}', [Admin\AcademyQuizController::class, 'destroyQuestion'])->whereNumber('question')->name('academy.courses.quiz.questions.destroy');
            Route::patch('academy/avis/{review}', [Admin\AcademyReviewController::class, 'toggle'])->whereNumber('review')->name('academy.courses.reviews.toggle');
            Route::delete('academy/avis/{review}', [Admin\AcademyReviewController::class, 'destroy'])->whereNumber('review')->name('academy.courses.reviews.destroy');
            Route::get('academy/commandes', [Admin\AcademyOrderController::class, 'index'])->name('academy.orders.index');
            Route::post('academy/commandes/{order}/payer', [Admin\AcademyOrderController::class, 'pay'])->whereNumber('order')->name('academy.orders.pay');
            Route::post('academy/commandes/{order}/annuler', [Admin\AcademyOrderController::class, 'cancel'])->whereNumber('order')->name('academy.orders.cancel');
            Route::post('academy/commandes/{order}/rembourser', [Admin\AcademyOrderController::class, 'refund'])->whereNumber('order')->name('academy.orders.refund');
            Route::get('academy/reglages', [Admin\AcademySettingsController::class, 'edit'])->name('academy.settings.edit');
            Route::put('academy/reglages', [Admin\AcademySettingsController::class, 'update'])->name('academy.settings.update');
            Route::post('academy/reglages/abonnements', [Admin\AcademySettingsController::class, 'storePlan'])->name('academy.settings.plans.store');
            Route::put('academy/reglages/abonnements/{plan}', [Admin\AcademySettingsController::class, 'updatePlan'])->whereNumber('plan')->name('academy.settings.plans.update');
            Route::delete('academy/reglages/abonnements/{plan}', [Admin\AcademySettingsController::class, 'destroyPlan'])->whereNumber('plan')->name('academy.settings.plans.destroy');
            Route::put('etablissements/{institution}', [Admin\InstitutionController::class, 'update'])->name('institutions.update');
            Route::delete('etablissements/{institution}', [Admin\InstitutionController::class, 'destroy'])->name('institutions.destroy');
            Route::get('entreprises', [Admin\CompanyController::class, 'index'])->name('companies.index');
            Route::post('entreprises', [Admin\CompanyController::class, 'store'])->name('companies.store');
            Route::put('entreprises/{company}', [Admin\CompanyController::class, 'update'])->name('companies.update');
            Route::delete('entreprises/{company}', [Admin\CompanyController::class, 'destroy'])->name('companies.destroy');
            Route::get('partenaires', [Admin\PartnerController::class, 'index'])->name('partners.index');
            Route::post('partenaires', [Admin\PartnerController::class, 'store'])->name('partners.store');
            Route::put('partenaires/{partner}', [Admin\PartnerController::class, 'update'])->name('partners.update');
            Route::delete('partenaires/{partner}', [Admin\PartnerController::class, 'destroy'])->name('partners.destroy');
            Route::get('projets', [Admin\ProjectController::class, 'index'])->name('projects.index');
            Route::get('projets/{scope}/modifier', [Admin\ProjectController::class, 'edit'])->name('projects.edit');
            Route::put('projets/{scope}', [Admin\ProjectController::class, 'update'])->name('projects.update');
            Route::delete('projets/{scope}', [Admin\ProjectController::class, 'destroy'])->name('projects.destroy');
            Route::get('actualites', [Admin\NewsController::class, 'index'])->name('news.index');
            Route::get('actualites/nouvelle', [Admin\NewsController::class, 'create'])->name('news.create');
            Route::post('actualites', [Admin\NewsController::class, 'store'])->name('news.store');
            Route::get('actualites/{news}/modifier', [Admin\NewsController::class, 'edit'])->whereNumber('news')->name('news.edit');
            Route::put('actualites/{news}', [Admin\NewsController::class, 'update'])->whereNumber('news')->name('news.update');
            Route::delete('actualites/{news}', [Admin\NewsController::class, 'destroy'])->whereNumber('news')->name('news.destroy');
            Route::get('info-session', [Admin\InfoSessionController::class, 'edit'])->name('info-session.edit');
            Route::put('info-session', [Admin\InfoSessionController::class, 'update'])->name('info-session.update');
            Route::get('messages', [Admin\ContactMessageController::class, 'index'])->name('messages.index');
            Route::patch('messages/{message}', [Admin\ContactMessageController::class, 'update'])->name('messages.update');
            Route::delete('messages/{message}', [Admin\ContactMessageController::class, 'destroy'])->name('messages.destroy');
            Route::get('newsletter', [Admin\NewsletterController::class, 'index'])->name('newsletter.index');
            Route::get('newsletter/export', [Admin\NewsletterController::class, 'export'])->name('newsletter.export');
            Route::delete('newsletter/{subscriber}', [Admin\NewsletterController::class, 'destroy'])->name('newsletter.destroy');

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

        // Mentorat : tickets (manager mentor, selon la matrice du poste) et annuaire des mentors. Les actions sur un ticket
        // (qualifier, affecter, clore…) sont les routes `mentoring.*`, qui contrôlent elles-mêmes le rôle de l'utilisateur.
        Route::middleware('admin.can:mentoring.manage,mentoring.roster')->prefix('mentorat')->name('mentoring.mentors.')->group(function (): void {
            Route::get('mentors', [Admin\MentoringController::class, 'mentors'])->name('index');
            Route::post('mentors', [Admin\MentoringController::class, 'storeMentor'])->name('store');
            Route::patch('mentors/{mentor}', [Admin\MentoringController::class, 'updateMentor'])->whereNumber('mentor')->name('update');
            Route::delete('mentors/{mentor}', [Admin\MentoringController::class, 'destroyMentor'])->whereNumber('mentor')->name('destroy');
        });

        Route::middleware('admin.can:mentoring.manage')->prefix('mentorat')->name('mentoring.tickets.')->group(function (): void {
            Route::get('/', [Admin\MentoringController::class, 'index'])->name('index');
            Route::get('{ticket}', [Admin\MentoringController::class, 'show'])->whereNumber('ticket')->name('show');
        });

        // Communaute
        Route::middleware('admin.can:entity.manage,membership.manage')->group(function (): void {
            Route::get('utilisateurs', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('utilisateurs/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        });

        Route::middleware('admin.can:membership.manage')->group(function (): void {
            Route::get('adhesions', [Admin\MembershipController::class, 'index'])->name('memberships.index');
            Route::patch('adhesions/{membership}', [Admin\MembershipController::class, 'update'])->name('memberships.update');
            Route::get('membres', [Admin\EntityMemberController::class, 'index'])->name('members.index');
            Route::post('membres', [Admin\EntityMemberController::class, 'store'])->name('members.store');
            Route::patch('membres/{membership}', [Admin\EntityMemberController::class, 'update'])->name('members.update');
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

            // Tunivisions Academy : calendrier des sessions en direct
            Route::get('academy/sessions', [Admin\AcademySessionController::class, 'index'])->name('academy.sessions.index');
            Route::post('academy/sessions', [Admin\AcademySessionController::class, 'store'])->name('academy.sessions.store');
            Route::put('academy/sessions/{session}', [Admin\AcademySessionController::class, 'update'])->whereNumber('session')->name('academy.sessions.update');
            Route::delete('academy/sessions/{session}', [Admin\AcademySessionController::class, 'destroy'])->whereNumber('session')->name('academy.sessions.destroy');
        });
    });
