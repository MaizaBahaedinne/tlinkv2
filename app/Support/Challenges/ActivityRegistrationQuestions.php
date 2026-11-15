<?php

namespace App\Support\Challenges;

/**
 * Questions types proposées à l'organisateur pour le formulaire d'inscription, selon le type d'activité.
 * Un clic les ajoute au formulaire ; chacune reste modifiable.
 */
final class ActivityRegistrationQuestions
{
    private const COMMON = [
        ['label' => 'Téléphone', 'type' => 'text', 'required' => true, 'options' => []],
        ['label' => 'Établissement / entreprise', 'type' => 'text', 'required' => false, 'options' => []],
        ['label' => 'Comment avez-vous connu l’activité ?', 'type' => 'choice', 'required' => false, 'options' => ['Réseaux sociaux', 'Ami ou collègue', 'Club', 'Affiche', 'Autre']],
    ];

    private const BY_TYPE = [
        'conference' => [
            ['label' => 'Niveau d’études / fonction', 'type' => 'text', 'required' => false, 'options' => []],
            ['label' => 'Une question à poser aux intervenants ?', 'type' => 'textarea', 'required' => false, 'options' => []],
        ],
        'training' => [
            ['label' => 'Niveau actuel', 'type' => 'choice', 'required' => true, 'options' => ['Débutant', 'Intermédiaire', 'Avancé']],
            ['label' => 'Apportez-vous votre ordinateur ?', 'type' => 'checkbox', 'required' => false, 'options' => []],
            ['label' => 'Attentes de la formation', 'type' => 'textarea', 'required' => false, 'options' => []],
        ],
        'event' => [
            ['label' => 'Venez-vous accompagné(e) ?', 'type' => 'choice', 'required' => false, 'options' => ['Non', '1 personne', '2 personnes ou plus']],
            ['label' => 'Restrictions alimentaires / allergies', 'type' => 'text', 'required' => false, 'options' => []],
        ],
        'teambuilding' => [
            ['label' => 'Taille de t-shirt', 'type' => 'choice', 'required' => true, 'options' => ['S', 'M', 'L', 'XL', 'XXL']],
            ['label' => 'Restrictions alimentaires / allergies', 'type' => 'text', 'required' => false, 'options' => []],
            ['label' => 'Problème de santé à signaler', 'type' => 'text', 'required' => false, 'options' => []],
        ],
        'camping' => [
            ['label' => 'Taille de t-shirt', 'type' => 'choice', 'required' => true, 'options' => ['S', 'M', 'L', 'XL', 'XXL']],
            ['label' => 'Restrictions alimentaires / allergies', 'type' => 'text', 'required' => false, 'options' => []],
            ['label' => 'Problème de santé à signaler', 'type' => 'text', 'required' => false, 'options' => []],
            ['label' => 'Contact d’urgence (nom et téléphone)', 'type' => 'text', 'required' => true, 'options' => []],
            ['label' => 'Moyen de transport', 'type' => 'choice', 'required' => true, 'options' => ['Transport organisé', 'Voiture personnelle', 'Covoiturage']],
            ['label' => 'Autorisation parentale signée (mineurs)', 'type' => 'checkbox', 'required' => false, 'options' => []],
        ],
        'competition' => [
            ['label' => 'Nom de l’équipe', 'type' => 'text', 'required' => true, 'options' => []],
            ['label' => 'Membres de l’équipe', 'type' => 'textarea', 'required' => false, 'options' => []],
            ['label' => 'Taille de t-shirt', 'type' => 'choice', 'required' => false, 'options' => ['S', 'M', 'L', 'XL', 'XXL']],
        ],
    ];

    /**
     * @return array<string, array<int, array{label: string, type: string, required: bool, options: array<int, string>}>>
     */
    public static function all(): array
    {
        $all = [];

        foreach (array_keys(\App\Models\ClubActivity::TYPES) as $type) {
            $all[$type] = array_merge(self::BY_TYPE[$type] ?? self::BY_TYPE['event'], self::COMMON);
        }

        return $all;
    }
}
