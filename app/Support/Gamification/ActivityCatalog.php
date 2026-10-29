<?php

namespace App\Support\Gamification;

use App\Models\AcademyEnrollment;
use App\Models\AcademyLessonCompletion;
use App\Models\AcademyQuizAttempt;
use App\Models\Comment;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Profile;
use App\Models\User;
use App\Support\Academy\AcademyLearning;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalogue des activités mesurables par un point de contrôle.
 *
 * Pour mesurer une nouvelle activité de la plateforme, ajouter UNE entrée dans `triggers()` :
 *  - `label` / `group` : affichage dans l'administration ;
 *  - `count` : fonction `fn (int $userId): int` qui calcule l'avancement à partir des données (source de vérité,
 *    donc les activités passées sont comptées automatiquement) ;
 *  - `models` : modèles dont l'enregistrement déclenche le recalcul, avec la fonction qui en tire l'identifiant du membre
 *    (retourner null pour ignorer l'événement).
 * Les badges et leurs missions se règlent ensuite depuis l'administration, sans autre code.
 */
final class ActivityCatalog
{
    /**
     * @return array<string, array{label: string, group: string, count: \Closure, models: array<class-string<Model>, \Closure>}>
     */
    public static function triggers(): array
    {
        $viaUser = fn (Model $model): ?int => $model->user_id !== null ? (int) $model->user_id : null;

        return [
            'profile.photo' => [
                'label' => 'Ajouter une photo de profil',
                'group' => 'Profil',
                'count' => fn (int $userId): int => Profile::query()->where('user_id', $userId)->whereNotNull('photo_path')->where('photo_path', '!=', '')->exists() ? 1 : 0,
                'models' => [Profile::class => $viaUser],
            ],
            'profile.presented' => [
                'label' => 'Renseigner son titre et sa bio',
                'group' => 'Profil',
                'count' => fn (int $userId): int => Profile::query()->where('user_id', $userId)
                    ->whereNotNull('headline')->where('headline', '!=', '')
                    ->whereNotNull('bio')->where('bio', '!=', '')
                    ->exists() ? 1 : 0,
                'models' => [Profile::class => $viaUser],
            ],
            'onboarding.completed' => [
                'label' => 'Valider son compte (info session et quiz)',
                'group' => 'Profil',
                'count' => fn (int $userId): int => User::query()->whereKey($userId)->whereNotNull('onboarding_completed_at')->exists() ? 1 : 0,
                'models' => [User::class => fn (Model $user): ?int => $user->wasChanged('onboarding_completed_at') ? (int) $user->id : null],
            ],
            'post.created' => [
                'label' => 'Publications publiées',
                'group' => 'Fil d’actualité',
                'count' => fn (int $userId): int => Post::query()->where('user_id', $userId)->count(),
                'models' => [Post::class => $viaUser],
            ],
            'comment.created' => [
                'label' => 'Commentaires écrits',
                'group' => 'Fil d’actualité',
                'count' => fn (int $userId): int => Comment::query()->where('user_id', $userId)->count(),
                'models' => [Comment::class => $viaUser],
            ],
            'reaction.given' => [
                'label' => 'Réactions données',
                'group' => 'Fil d’actualité',
                'count' => fn (int $userId): int => PostReaction::query()->where('user_id', $userId)->count(),
                'models' => [PostReaction::class => $viaUser],
            ],
            'event.registered' => [
                'label' => 'Inscriptions à des événements',
                'group' => 'Événements',
                'count' => fn (int $userId): int => EventRegistration::query()->where('user_id', $userId)->whereNotIn('status', ['cancelled', 'rejected'])->count(),
                'models' => [EventRegistration::class => $viaUser],
            ],
            'event.attended' => [
                'label' => 'Événements auxquels le membre a participé',
                'group' => 'Événements',
                'count' => fn (int $userId): int => EventRegistration::query()->where('user_id', $userId)->where('status', 'attended')->count(),
                'models' => [EventRegistration::class => $viaUser],
            ],
            'academy.enrolled' => [
                'label' => 'Cours Academy suivis (inscriptions)',
                'group' => 'Tunivisions Academy',
                'count' => fn (int $userId): int => AcademyEnrollment::query()->where('user_id', $userId)->count(),
                'models' => [AcademyEnrollment::class => $viaUser],
            ],
            'academy.lessons_completed' => [
                'label' => 'Leçons Academy terminées',
                'group' => 'Tunivisions Academy',
                'count' => fn (int $userId): int => AcademyLessonCompletion::query()->where('user_id', $userId)->count(),
                'models' => [AcademyLessonCompletion::class => $viaUser],
            ],
            'academy.courses_completed' => [
                'label' => 'Cours Academy terminés',
                'group' => 'Tunivisions Academy',
                'count' => fn (int $userId): int => AcademyEnrollment::query()->where('user_id', $userId)->whereNotNull('completed_at')->count(),
                'models' => [AcademyEnrollment::class => $viaUser],
            ],
            'academy.quizzes_passed' => [
                'label' => 'Quiz Academy réussis (quiz distincts)',
                'group' => 'Tunivisions Academy',
                'count' => fn (int $userId): int => AcademyQuizAttempt::query()->where('user_id', $userId)->where('status', 'passed')->distinct()->count('quiz_id'),
                'models' => [AcademyQuizAttempt::class => $viaUser],
            ],
            'academy.points' => [
                'label' => 'Points Academy (leçons terminées + bonus de fin de cours)',
                'group' => 'Tunivisions Academy',
                'count' => fn (int $userId): int => AcademyLearning::points($userId),
                'models' => [AcademyLessonCompletion::class => $viaUser, AcademyEnrollment::class => $viaUser],
            ],
            'membership.approved' => [
                'label' => 'Adhésions validées (entités et clubs)',
                'group' => 'Fondation',
                'count' => fn (int $userId): int => Membership::query()->where('user_id', $userId)->where('status', 'approved')->count(),
                'models' => [Membership::class => $viaUser],
            ],
            'office.held' => [
                'label' => 'Postes occupés au sein de la Fondation',
                'group' => 'Fondation',
                'count' => fn (int $userId): int => OrgAssignment::query()
                    ->whereHas('person', fn ($query) => $query->where('user_id', $userId))
                    ->whereHas('node', fn ($query) => $query->where('kind', 'position'))
                    ->count(),
                'models' => [OrgAssignment::class => fn (Model $assignment): ?int => $assignment->person?->user_id !== null ? (int) $assignment->person->user_id : null],
            ],
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::triggers());
    }

    public static function label(string $key): string
    {
        return self::triggers()[$key]['label'] ?? $key.' (activité inconnue)';
    }

    /**
     * Avancement brut d'un membre sur une activité (0 pour une activité inconnue).
     */
    public static function count(string $key, int $userId): int
    {
        $count = self::triggers()[$key]['count'] ?? null;

        return $count === null ? 0 : max(0, (int) $count($userId));
    }

    /**
     * Activités regroupées par catégorie, pour les listes déroulantes de l'administration.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::triggers() as $key => $trigger) {
            $groups[$trigger['group']][$key] = $trigger['label'];
        }

        return $groups;
    }

    /**
     * Branche le recalcul sur les événements d'enregistrement des modèles (appelé au démarrage de l'application).
     */
    public static function listen(): void
    {
        $byModel = [];

        foreach (self::triggers() as $key => $trigger) {
            foreach ($trigger['models'] as $model => $resolveUser) {
                $byModel[$model]['keys'][] = $key;
                $byModel[$model]['user'] = $resolveUser;
            }
        }

        foreach ($byModel as $model => $config) {
            $model::saved(function (Model $instance) use ($config): void {
                $userId = ($config['user'])($instance);

                if ($userId !== null) {
                    GamificationEngine::refreshSafely($userId, $config['keys']);
                }
            });
        }
    }
}
