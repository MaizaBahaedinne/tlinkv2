<?php

namespace App\Support;

use App\Models\Profile;
use Illuminate\Support\Str;

/**
 * Statut actuel d'un membre, demandé pendant l'intégration : élève, étudiant (avec son niveau), en recherche d'emploi,
 * salarié, chef d'entreprise (société, logo, date de début) ou membre de la diaspora (pays de résidence).
 */
final class CurrentStatus
{
    public const PUPIL = 'pupil';

    public const STUDENT = 'student';

    public const JOB_SEEKER = 'job_seeker';

    public const EMPLOYEE = 'employee';

    public const ENTREPRENEUR = 'entrepreneur';

    public const DIASPORA = 'diaspora';

    /** Rôle inscrit dans l'expérience professionnelle d'un chef d'entreprise. */
    public const ENTREPRENEUR_ROLE = 'Chef d’entreprise';

    public const KINDS = [
        self::PUPIL => 'Élève',
        self::STUDENT => 'Étudiant',
        self::JOB_SEEKER => 'À la recherche d’un emploi',
        self::EMPLOYEE => 'Salarié',
        self::ENTREPRENEUR => 'Chef d’entreprise',
        self::DIASPORA => 'Diaspora (je vis à l’étranger)',
    ];

    public const PUPIL_LEVELS = [
        'eleve-1' => '1re année',
        'eleve-2' => '2e année',
        'eleve-3' => '3e année',
        'baccalaureat' => 'Baccalauréat',
    ];

    /** @var array<string, array<string, string>> */
    public const STUDENT_LEVELS = [
        'Licence' => ['licence-1' => 'Licence 1', 'licence-2' => 'Licence 2', 'licence-3' => 'Licence 3'],
        'Master' => ['master-1' => 'Master 1', 'master-2' => 'Master 2'],
        'Ingénieur' => ['ingenieur-1' => 'Ingénieur 1', 'ingenieur-2' => 'Ingénieur 2', 'ingenieur-3' => 'Ingénieur 3'],
        'Doctorat' => ['doctorat' => 'Doctorat'],
    ];

    /** Pays de résidence possibles (la Tunisie est exclue : la diaspora vit à l'étranger). */
    private const COUNTRIES = [
        'Afghanistan', 'Afrique du Sud', 'Albanie', 'Algérie', 'Allemagne', 'Andorre', 'Angola', 'Antigua-et-Barbuda', 'Arabie saoudite', 'Argentine',
        'Arménie', 'Australie', 'Autriche', 'Azerbaïdjan', 'Bahamas', 'Bahreïn', 'Bangladesh', 'Barbade', 'Belgique', 'Belize', 'Bénin', 'Bhoutan',
        'Biélorussie', 'Birmanie', 'Bolivie', 'Bosnie-Herzégovine', 'Botswana', 'Brésil', 'Brunei', 'Bulgarie', 'Burkina Faso', 'Burundi', 'Cambodge',
        'Cameroun', 'Canada', 'Cap-Vert', 'Chili', 'Chine', 'Chypre', 'Colombie', 'Comores', 'Congo', 'Corée du Nord', 'Corée du Sud', 'Costa Rica',
        'Côte d’Ivoire', 'Croatie', 'Cuba', 'Danemark', 'Djibouti', 'Dominique', 'Égypte', 'Émirats arabes unis', 'Équateur', 'Érythrée', 'Espagne',
        'Estonie', 'Eswatini', 'États-Unis', 'Éthiopie', 'Fidji', 'Finlande', 'France', 'Gabon', 'Gambie', 'Géorgie', 'Ghana', 'Grèce', 'Grenade',
        'Guatemala', 'Guinée', 'Guinée équatoriale', 'Guinée-Bissau', 'Guyana', 'Haïti', 'Honduras', 'Hongrie', 'Inde', 'Indonésie', 'Irak', 'Iran',
        'Irlande', 'Islande', 'Israël', 'Italie', 'Jamaïque', 'Japon', 'Jordanie', 'Kazakhstan', 'Kenya', 'Kirghizistan', 'Kiribati', 'Koweït', 'Laos',
        'Lesotho', 'Lettonie', 'Liban', 'Libéria', 'Libye', 'Liechtenstein', 'Lituanie', 'Luxembourg', 'Macédoine du Nord', 'Madagascar', 'Malaisie',
        'Malawi', 'Maldives', 'Mali', 'Malte', 'Maroc', 'Îles Marshall', 'Maurice', 'Mauritanie', 'Mexique', 'Micronésie', 'Moldavie', 'Monaco',
        'Mongolie', 'Monténégro', 'Mozambique', 'Namibie', 'Nauru', 'Népal', 'Nicaragua', 'Niger', 'Nigeria', 'Norvège', 'Nouvelle-Zélande', 'Oman',
        'Ouganda', 'Ouzbékistan', 'Pakistan', 'Palaos', 'Palestine', 'Panama', 'Papouasie-Nouvelle-Guinée', 'Paraguay', 'Pays-Bas', 'Pérou',
        'Philippines', 'Pologne', 'Portugal', 'Qatar', 'République centrafricaine', 'République démocratique du Congo', 'République dominicaine',
        'République tchèque', 'Roumanie', 'Royaume-Uni', 'Russie', 'Rwanda', 'Saint-Christophe-et-Niévès', 'Sainte-Lucie', 'Saint-Marin',
        'Saint-Vincent-et-les-Grenadines', 'Îles Salomon', 'Salvador', 'Samoa', 'Sao Tomé-et-Principe', 'Sénégal', 'Serbie', 'Seychelles',
        'Sierra Leone', 'Singapour', 'Slovaquie', 'Slovénie', 'Somalie', 'Soudan', 'Soudan du Sud', 'Sri Lanka', 'Suède', 'Suisse', 'Suriname',
        'Syrie', 'Tadjikistan', 'Tanzanie', 'Tchad', 'Thaïlande', 'Timor oriental', 'Togo', 'Tonga', 'Trinité-et-Tobago', 'Turkménistan', 'Turquie',
        'Tuvalu', 'Ukraine', 'Uruguay', 'Vanuatu', 'Vatican', 'Venezuela', 'Viêt Nam', 'Yémen', 'Zambie', 'Zimbabwe',
    ];

    /**
     * Pays de résidence à l'étranger, dans l'ordre alphabétique (sans tenir compte des accents).
     *
     * @return list<string>
     */
    public static function countries(): array
    {
        return collect(self::COUNTRIES)
            ->sortBy(fn (string $name): string => Str::lower(Str::ascii($name)), SORT_NATURAL)
            ->values()
            ->all();
    }

    /** Statuts qui demandent un niveau (élève, étudiant). */
    public static function usesLevel(?string $kind): bool
    {
        return in_array($kind, [self::PUPIL, self::STUDENT], true);
    }

    /**
     * Niveaux possibles pour un statut, clé => libellé.
     *
     * @return array<string, string>
     */
    public static function levels(?string $kind): array
    {
        return match ($kind) {
            self::PUPIL => self::PUPIL_LEVELS,
            self::STUDENT => array_merge(...array_values(self::STUDENT_LEVELS)),
            default => [],
        };
    }

    /**
     * Ce qu'il reste à renseigner pour que le statut soit complet.
     *
     * @return list<string>
     */
    public static function missing(?Profile $profile): array
    {
        $kind = $profile?->status_kind;

        if (! is_string($kind) || ! isset(self::KINDS[$kind])) {
            return ['votre statut actuel'];
        }

        $missing = [];

        if (self::usesLevel($kind) && ! isset(self::levels($kind)[(string) $profile->status_level])) {
            $missing[] = 'votre niveau';
        }

        if ($kind === self::DIASPORA && ! in_array($profile->status_country, self::COUNTRIES, true)) {
            $missing[] = 'le pays où vous vivez';
        }

        if ($kind === self::ENTREPRENEUR && blank($profile->status_company_id)) {
            $missing[] = 'votre société';
        }

        return $missing;
    }

    public static function complete(?Profile $profile): bool
    {
        return self::missing($profile) === [];
    }
}
