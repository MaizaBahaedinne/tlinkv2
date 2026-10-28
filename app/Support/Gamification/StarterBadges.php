<?php

namespace App\Support\Gamification;

use App\Models\Badge;

/**
 * Badges de départ (modifiables et extensibles depuis l'administration).
 */
final class StarterBadges
{
    /**
     * Crée les badges de départ ; ne fait rien si des badges existent déjà.
     */
    public static function install(): bool
    {
        if (Badge::query()->exists()) {
            return false;
        }

        $badges = [
            ['bienvenue', 'Bienvenue', 'bronze', 'fa-solid fa-seedling', 'Les premiers pas sur T.Link.', [
                ['Compléter son profil', 'Présentez-vous à la communauté.', [
                    ['Ajouter une photo de profil', 'profile.photo', 1],
                    ['Renseigner son titre et sa bio', 'profile.presented', 1],
                ]],
                ['Valider son compte', 'Suivez l’info session et réussissez le quiz.', [
                    ['Valider son compte', 'onboarding.completed', 1],
                ]],
            ]],
            ['prise-de-parole', 'Prise de parole', 'bronze', 'fa-solid fa-comments', 'Participer pour la première fois à la vie du fil.', [
                ['Participer au fil', 'Publiez, commentez et réagissez.', [
                    ['Publier un post', 'post.created', 1],
                    ['Écrire un commentaire', 'comment.created', 1],
                    ['Donner une réaction', 'reaction.given', 1],
                ]],
            ]],
            ['membre-actif', 'Membre actif', 'silver', 'fa-solid fa-fire', 'Animer régulièrement la communauté.', [
                ['Animer la communauté', 'Soyez présent(e) dans le fil au quotidien.', [
                    ['Publier 5 posts', 'post.created', 5],
                    ['Écrire 10 commentaires', 'comment.created', 10],
                    ['Donner 20 réactions', 'reaction.given', 20],
                ]],
            ]],
            ['engage', 'Engagé(e)', 'gold', 'fa-solid fa-handshake-angle', 'S’investir dans la vie de la Fondation.', [
                ['Rejoindre une entité', 'Faites-vous valider dans un club ou une entité.', [
                    ['Adhésion validée', 'membership.approved', 1],
                ]],
                ['Vivre les événements', 'Participez aux événements de la Fondation.', [
                    ['S’inscrire à 3 événements', 'event.registered', 3],
                    ['Participer à 1 événement', 'event.attended', 1],
                ]],
            ]],
            ['leader', 'Leader', 'platinum', 'fa-solid fa-crown', 'Porter la Fondation et rayonner.', [
                ['Porter la Fondation', 'Occupez un poste au sein de l’organigramme.', [
                    ['Occuper un poste', 'office.held', 1],
                ]],
                ['Rayonner', 'Soyez un moteur de la communauté.', [
                    ['Participer à 5 événements', 'event.attended', 5],
                    ['Publier 20 posts', 'post.created', 20],
                ]],
            ]],
        ];

        foreach ($badges as $badgeIndex => [$slug, $name, $tier, $icon, $description, $missions]) {
            $badge = Badge::query()->create([
                'slug' => $slug,
                'name' => $name,
                'tier' => $tier,
                'icon' => $icon,
                'description' => $description,
                'sort_order' => $badgeIndex + 1,
            ]);

            foreach ($missions as $missionIndex => [$title, $missionDescription, $checkpoints]) {
                $mission = $badge->missions()->create([
                    'title' => $title,
                    'description' => $missionDescription,
                    'sort_order' => $missionIndex + 1,
                ]);

                foreach ($checkpoints as $checkpointIndex => [$checkpointTitle, $trigger, $target]) {
                    $mission->checkpoints()->create([
                        'title' => $checkpointTitle,
                        'trigger_key' => $trigger,
                        'target' => $target,
                        'sort_order' => $checkpointIndex + 1,
                    ]);
                }
            }
        }

        return true;
    }
}
