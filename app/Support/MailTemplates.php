<?php

namespace App\Support;

use App\Models\MailLog;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Modèles des e-mails automatiques (Admin > Messagerie > Modèles) : objet, salutation, corps, bouton et signature
 * modifiables par l'administration, stockés en base. Les valeurs d'origine (ci-dessous) s'appliquent tant qu'un modèle
 * n'a pas été personnalisé. Les variables s'écrivent entre accolades : {prenom}, {code}…
 *
 * Dans le corps : une ligne par paragraphe ; la ligne « {bouton} » place le bouton d'action (sinon il suit le texte).
 */
final class MailTemplates
{
    public const GROUP = 'mail';

    public const KEY = 'templates';

    public const BUTTON_PLACEHOLDER = '{bouton}';

    /**
     * @var array<string, array{label: string, description: string, variables: array<string, string>, required: array<int, string>, button: bool, defaults: array<string, string>, sample: array<string, string>}>
     */
    public const CATALOG = [
        'verification_code' => [
            'label' => 'Code de confirmation de l’adresse e-mail',
            'description' => 'Envoyé à chaque nouveau membre pour confirmer son adresse (code à 6 chiffres).',
            'variables' => [
                'prenom' => 'Prénom du membre',
                'code' => 'Code de confirmation à 6 chiffres',
                'minutes' => 'Durée de validité du code, en minutes',
            ],
            'required' => ['code'],
            'button' => false,
            'defaults' => [
                'subject' => 'Votre code de confirmation : {code} | T.Link',
                'greeting' => 'Bienvenue {prenom} !',
                'body' => "Pour poursuivre votre intégration à la Fondation Tunivisions, saisissez ce code de confirmation dans T.Link :\n**{code}**\nCe code est valable {minutes} minutes. Si vous n’avez pas créé de compte, ignorez ce message.",
                'button_label' => '',
                'salutation' => 'L’équipe Tunivisions',
            ],
            'sample' => ['prenom' => 'Amel', 'code' => '482913', 'minutes' => '15'],
        ],
        'position_invitation' => [
            'label' => 'Invitation à candidater à un poste',
            'description' => 'Envoyé depuis l’organigramme aux personnes invitées à candidater à un poste vacant.',
            'variables' => [
                'prenom' => 'Prénom de la personne invitée',
                'nom' => 'Nom de la personne invitée',
                'poste' => 'Intitulé du poste, suivi de son périmètre',
                'perimetre' => 'Périmètre du poste (vide s’il n’en a pas)',
                'jours' => 'Durée de validité du lien, en jours',
                'bouton' => 'Emplacement du bouton (à placer seul sur sa ligne)',
            ],
            'required' => [],
            'button' => true,
            'defaults' => [
                'subject' => 'Invitation à candidater : {poste} | T.Link',
                'greeting' => 'Bonjour {prenom} !',
                'body' => "La Fondation Tunivisions vous invite à candidater au poste « {poste} ».\nCréez votre compte T.Link en quelques minutes, puis déposez votre candidature : elle sera examinée par l’équipe.\n{bouton}\nCe lien est personnel et valable {jours} jours. Si cette invitation ne vous concerne pas, ignorez ce message.",
                'button_label' => 'Créer mon compte et candidater',
                'salutation' => 'L’équipe Tunivisions',
            ],
            'sample' => ['prenom' => 'Amel', 'nom' => 'Ben Salah', 'poste' => 'Responsable communication (University)', 'perimetre' => 'University', 'jours' => '14'],
        ],
    ];

    public const FIELDS = ['subject', 'greeting', 'body', 'button_label', 'salutation'];

    /**
     * Modèle effectif : valeurs enregistrées par-dessus celles d'origine.
     *
     * @return array<string, string>
     */
    public static function get(string $key): array
    {
        $defaults = self::CATALOG[$key]['defaults'];
        $saved = self::all()[$key] ?? [];

        $template = [];

        foreach (self::FIELDS as $field) {
            $value = $saved[$field] ?? null;
            $template[$field] = is_string($value) && ($value !== '' || $field === 'button_label') ? $value : $defaults[$field];
        }

        return $template;
    }

    public static function isCustomized(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * @param array<string, string> $values
     */
    public static function save(string $key, array $values): void
    {
        $all = self::all();
        $all[$key] = array_intersect_key($values, array_flip(self::FIELDS));

        self::store($all);
    }

    /** Revient au modèle d'origine. */
    public static function reset(string $key): void
    {
        $all = self::all();
        unset($all[$key]);

        self::store($all);
    }

    /**
     * Construit le message à partir du modèle et des valeurs de variables.
     *
     * @param array<string, string> $variables
     */
    public static function message(string $key, array $variables, ?string $actionUrl = null, ?array $template = null): MailMessage
    {
        $template ??= self::get($key);
        $map = [];

        foreach ($variables as $name => $value) {
            $map['{'.$name.'}'] = (string) $value;
        }

        $fill = fn (string $text): string => strtr($text, $map);

        $mail = (new MailMessage())
            ->subject($fill($template['subject']))
            ->greeting($fill($template['greeting']))
            ->salutation($fill($template['salutation']))
            ->withSymfonyMessage(fn ($message) => $message->getHeaders()->addTextHeader(MailLog::TEMPLATE_HEADER, $key));

        $wantsButton = $actionUrl !== null && self::CATALOG[$key]['button'] && trim($template['button_label']) !== '';
        $placed = false;

        foreach (preg_split('/\R/', $template['body']) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if ($line === self::BUTTON_PLACEHOLDER) {
                if ($wantsButton && ! $placed) {
                    $mail->action($fill($template['button_label']), $actionUrl);
                    $placed = true;
                }

                continue;
            }

            $mail->line($fill($line));
        }

        if ($wantsButton && ! $placed) {
            $mail->action($fill($template['button_label']), $actionUrl);
        }

        return $mail;
    }

    /** Message d'exemple pour l'aperçu et l'envoi de test. */
    public static function sample(string $key, ?array $template = null): MailMessage
    {
        return self::message($key, self::CATALOG[$key]['sample'], self::CATALOG[$key]['button'] ? url('/') : null, $template);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function all(): array
    {
        try {
            $value = Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->value('value');
        } catch (\Throwable) {
            return [];
        }

        $data = $value ? json_decode((string) $value, true) : null;

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array<string, string>> $all
     */
    private static function store(array $all): void
    {
        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($all, JSON_UNESCAPED_UNICODE)],
        );
    }
}
