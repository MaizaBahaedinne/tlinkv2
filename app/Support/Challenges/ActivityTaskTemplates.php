<?php

namespace App\Support\Challenges;

/**
 * Listes de tâches types, définies par le système : une par famille d'activité, avec les quatre équipes
 * (événement, marketing, RH, sponsoring). Le créateur d'une activité les reprend puis les adapte, les affecte et les suit.
 */
final class ActivityTaskTemplates
{
    public const TEAMS = [
        'event' => 'Événement',
        'marketing' => 'Marketing',
        'hr' => 'RH',
        'sponsoring' => 'Sponsoring',
    ];

    /** Type d'activité => famille de liste. */
    public const SET_OF_TYPE = [
        'conference' => 'conference',
        'training' => 'training',
        'event' => 'general',
        'party' => 'general',
        'other' => 'general',
        'teambuilding' => 'teambuilding',
        'camping' => 'camping',
        'competition' => 'competition',
    ];

    public const SETS = [
        'conference' => [
            'label' => 'Conférence',
            'description' => 'Conférence ou journée thématique avec intervenants.',
            'teams' => [
                'event' => [
                    'Définir le thème, les objectifs et le format de la conférence.',
                    'Identifier et confirmer les intervenants et modérateurs.',
                    'Réserver la salle et organiser le matériel audiovisuel.',
                    'Élaborer le programme détaillé et le déroulement de la journée.',
                    'Gérer les inscriptions, l’accueil et l’orientation des participants.',
                    'Coordonner le déroulement sur place et gérer les imprévus.',
                ],
                'marketing' => [
                    'Définir le public cible et le message principal.',
                    'Créer l’identité visuelle et les supports de communication.',
                    'Préparer le calendrier des publications et annonces.',
                    'Diffuser les invitations et la campagne sur les réseaux sociaux.',
                    'Publier les rappels, présentations des intervenants et informations pratiques.',
                    'Réaliser la couverture photo/vidéo et publier un récapitulatif.',
                ],
                'hr' => [
                    'Définir les besoins humains et les postes nécessaires.',
                    'Désigner les responsables des équipes et répartir les missions.',
                    'Recruter ou mobiliser les bénévoles et confirmer leur disponibilité.',
                    'Préparer le planning des équipes et les consignes de chaque poste.',
                    'Organiser un briefing général avant l’ouverture.',
                    'Suivre la présence des membres et recueillir leur retour après l’événement.',
                ],
                'sponsoring' => [
                    'Établir le budget prévisionnel et les besoins de financement.',
                    'Identifier les entreprises et partenaires potentiels.',
                    'Préparer le dossier de sponsoring et les offres de visibilité.',
                    'Contacter les prospects et assurer les relances.',
                    'Négocier et confirmer les accords et contributions.',
                    'Assurer les contreparties promises et envoyer un bilan aux sponsors.',
                ],
            ],
        ],
        'training' => [
            'label' => 'Formation / Workshop',
            'description' => 'Formation, atelier pratique ou session de développement.',
            'teams' => [
                'event' => [
                    'Définir les objectifs pédagogiques et les compétences visées.',
                    'Choisir le format, la durée, la date et le lieu de la formation.',
                    'Identifier le formateur et valider le contenu du programme.',
                    'Préparer la salle, les équipements et les supports pédagogiques.',
                    'Gérer les inscriptions et la liste des participants.',
                    'Animer la session et organiser l’évaluation à chaud.',
                ],
                'marketing' => [
                    'Définir le public cible et les prérequis de participation.',
                    'Créer le visuel et la description de la formation.',
                    'Préparer le formulaire d’inscription et les messages de confirmation.',
                    'Promouvoir la formation sur les canaux du club et de ses partenaires.',
                    'Publier les rappels et les informations pratiques aux inscrits.',
                    'Partager les moments clés et publier les résultats ou retours.',
                ],
                'hr' => [
                    'Identifier les besoins de formation des membres ou du public.',
                    'Définir les profils et le nombre de personnes nécessaires à l’organisation.',
                    'Affecter les responsables de l’accueil, de la coordination et de l’assistance.',
                    'Confirmer la disponibilité du formateur et de l’équipe organisatrice.',
                    'Organiser un briefing des équipes avant la session.',
                    'Collecter les évaluations et identifier les besoins de suivi.',
                ],
                'sponsoring' => [
                    'Lister les besoins financiers, matériels et pédagogiques.',
                    'Identifier les entreprises pouvant soutenir la formation.',
                    'Préparer une proposition de partenariat adaptée au thème.',
                    'Contacter les sponsors potentiels et présenter les bénéfices.',
                    'Confirmer les contributions et les engagements de visibilité.',
                    'Remercier les partenaires et transmettre un bilan de la formation.',
                ],
            ],
        ],
        'general' => [
            'label' => 'Événement général',
            'description' => 'Événement de club, lancement, journée portes ouvertes ou activité publique.',
            'teams' => [
                'event' => [
                    'Définir le concept, les objectifs et le public de l’événement.',
                    'Choisir la date, le lieu, le format et le programme.',
                    'Établir le budget et la liste des besoins logistiques.',
                    'Obtenir les autorisations nécessaires et confirmer les prestataires.',
                    'Organiser l’accueil, le déroulement et la coordination sur place.',
                    'Clôturer l’événement et rédiger le bilan organisationnel.',
                ],
                'marketing' => [
                    'Définir le positionnement et le message de l’événement.',
                    'Créer le nom, le visuel et les supports promotionnels.',
                    'Établir un calendrier de communication avant l’événement.',
                    'Lancer la campagne de promotion et gérer les inscriptions.',
                    'Assurer la couverture en direct et les interactions avec le public.',
                    'Publier les photos, vidéos et résultats après l’événement.',
                ],
                'hr' => [
                    'Évaluer les besoins en membres, bénévoles et responsables.',
                    'Créer les équipes et attribuer les rôles de chacun.',
                    'Établir les horaires, rotations et points de contact.',
                    'Préparer les consignes opérationnelles et le briefing.',
                    'Superviser les équipes pendant l’événement et réaffecter les ressources si besoin.',
                    'Évaluer la participation et la contribution des membres.',
                ],
                'sponsoring' => [
                    'Évaluer les besoins de financement et les ressources disponibles.',
                    'Construire les packs de sponsoring et les niveaux de visibilité.',
                    'Rechercher et contacter les sponsors et partenaires.',
                    'Négocier les contributions et formaliser les accords.',
                    'Coordonner la visibilité des sponsors pendant l’événement.',
                    'Fournir les preuves de visibilité et le bilan aux partenaires.',
                ],
            ],
        ],
        'teambuilding' => [
            'label' => 'Team building',
            'description' => 'Activité de cohésion, intégration et collaboration d’équipe.',
            'teams' => [
                'event' => [
                    'Définir les objectifs de cohésion et le nombre de participants.',
                    'Choisir les activités, le lieu, la date et le déroulement.',
                    'Préparer le matériel, les espaces et les règles de sécurité.',
                    'Constituer les équipes et préparer les défis ou jeux.',
                    'Animer les activités et gérer le timing.',
                    'Organiser un débriefing collectif et recueillir les impressions.',
                ],
                'marketing' => [
                    'Définir le thème et le ton de la communication interne.',
                    'Créer les invitations et les supports d’annonce.',
                    'Diffuser le programme et les informations pratiques.',
                    'Préparer les contenus pour mobiliser les participants.',
                    'Capturer les moments forts en photo et vidéo.',
                    'Partager un récapitulatif interne et valoriser la participation.',
                ],
                'hr' => [
                    'Identifier les besoins de cohésion ou d’intégration de l’équipe.',
                    'Définir les participants et gérer les confirmations.',
                    'Constituer des groupes équilibrés et attribuer les encadrants.',
                    'Préparer les règles de participation et les consignes de sécurité.',
                    'Assurer l’encadrement et le bien-être des participants.',
                    'Évaluer la cohésion, l’engagement et les retours après l’activité.',
                ],
                'sponsoring' => [
                    'Identifier les besoins matériels, alimentaires et logistiques.',
                    'Rechercher des partenaires locaux ou fournisseurs de services.',
                    'Préparer une proposition de soutien ou de partenariat en nature.',
                    'Négocier les réductions, dons ou prestations offertes.',
                    'Assurer la visibilité convenue des partenaires.',
                    'Remercier les partenaires et documenter les contributions reçues.',
                ],
            ],
        ],
        'camping' => [
            'label' => 'Camping / Retreat',
            'description' => 'Sortie, séjour, camping ou retraite de club.',
            'teams' => [
                'event' => [
                    'Définir la destination, les dates, le programme et la capacité.',
                    'Évaluer les risques, les autorisations et les conditions de sécurité.',
                    'Organiser le transport, l’hébergement, les repas et les équipements.',
                    'Préparer le planning des activités et les solutions de secours.',
                    'Coordonner le départ, le séjour et les activités sur place.',
                    'Organiser le retour et établir le bilan du séjour.',
                ],
                'marketing' => [
                    'Créer le concept et le visuel de l’expérience.',
                    'Annoncer le séjour et communiquer les critères de participation.',
                    'Préparer les informations pratiques et la liste des affaires à prévoir.',
                    'Gérer les annonces, rappels et échanges avec les participants.',
                    'Documenter le séjour avec photos et vidéos en respectant les autorisations.',
                    'Publier un récapitulatif et valoriser l’expérience collective.',
                ],
                'hr' => [
                    'Définir les responsables du séjour et les encadrants.',
                    'Collecter les inscriptions et les informations nécessaires des participants.',
                    'Répartir les participants en groupes et attribuer les référents.',
                    'Communiquer les règles, consignes et contacts d’urgence.',
                    'Suivre la présence, la sécurité et le bien-être pendant le séjour.',
                    'Recueillir les retours et évaluer l’expérience des participants.',
                ],
                'sponsoring' => [
                    'Établir le budget du séjour et les besoins à couvrir.',
                    'Identifier les partenaires transport, hébergement, restauration ou équipement.',
                    'Préparer les demandes de soutien financier ou en nature.',
                    'Négocier les tarifs, réductions et contributions.',
                    'Respecter les engagements de visibilité envers les partenaires.',
                    'Envoyer les remerciements et le bilan des partenariats.',
                ],
            ],
        ],
        'competition' => [
            'label' => 'Compétition / Challenge',
            'description' => 'Concours, tournoi, hackathon ou challenge entre participants.',
            'teams' => [
                'event' => [
                    'Définir le concept, les catégories, les règles et les critères d’évaluation.',
                    'Fixer le calendrier, les étapes et les modalités d’inscription.',
                    'Sélectionner le jury, les arbitres ou les évaluateurs.',
                    'Préparer les espaces, équipements et outils nécessaires.',
                    'Coordonner les épreuves et gérer les résultats.',
                    'Organiser la remise des prix et publier le classement final.',
                ],
                'marketing' => [
                    'Définir le nom, l’identité visuelle et le message du challenge.',
                    'Créer le règlement simplifié et les supports d’inscription.',
                    'Lancer la campagne de recrutement des participants.',
                    'Publier les rappels, portraits des jurés et informations clés.',
                    'Assurer la couverture des épreuves et partager les temps forts.',
                    'Annoncer les gagnants et publier le bilan de la compétition.',
                ],
                'hr' => [
                    'Définir les besoins en organisateurs, arbitres, jurés et bénévoles.',
                    'Recruter ou mobiliser les personnes nécessaires.',
                    'Attribuer les rôles et établir le planning des équipes.',
                    'Briefer les équipes sur les règles, procédures et critères.',
                    'Gérer les rotations, la disponibilité et les imprévus humains.',
                    'Évaluer le travail des équipes et recueillir leurs suggestions.',
                ],
                'sponsoring' => [
                    'Définir les prix, récompenses et besoins financiers ou matériels.',
                    'Identifier les marques et organisations pertinentes pour le challenge.',
                    'Préparer les offres de sponsoring et les espaces de visibilité.',
                    'Contacter les prospects et négocier les contributions.',
                    'Coordonner les stands, mentions et remises de prix des sponsors.',
                    'Remettre un bilan, les preuves de visibilité et les remerciements.',
                ],
            ],
        ],
    ];

    /** Clé de la liste type d'un type d'activité. */
    public static function setKey(?string $type): string
    {
        return self::SET_OF_TYPE[$type] ?? 'general';
    }

    /** @return array{label: string, description: string, teams: array<string, array<int, string>>} */
    public static function forType(?string $type): array
    {
        return self::SETS[self::setKey($type)];
    }

    /**
     * Tâches de la liste type, à plat : équipe, intitulé et ordre.
     *
     * @return array<int, array{team: string, label: string, sort_order: int}>
     */
    public static function tasksForType(?string $type): array
    {
        $tasks = [];
        $order = 0;

        foreach (self::forType($type)['teams'] as $team => $labels) {
            foreach ($labels as $label) {
                $tasks[] = ['team' => $team, 'label' => $label, 'sort_order' => $order++];
            }
        }

        return $tasks;
    }
}
