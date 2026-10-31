<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pages d'erreur : référence d'incident, explication pour le membre et rapport à transmettre à l'administrateur.
 *
 * Rien de sensible n'est exposé : ni message d'exception, ni trace, ni paramètres d'URL (qui peuvent contenir des jetons).
 * La référence est aussi inscrite dans les logs (erreurs 500 comprises), ce qui permet à l'administrateur de retrouver l'incident.
 */
class ErrorReport
{
    private const ATTRIBUTE = 'error_reference';

    /** Codes pour lesquels une page d'erreur affichée est aussi consignée dans les logs (le 404 et le 401 sont trop bruyants). */
    private const LOGGED_CODES = [400, 403, 405, 408, 413, 419, 429];

    /**
     * Explications par code : ce qui s'est passé, causes probables, gestes du membre, piste de résolution pour l'administrateur.
     *
     * @var array<int, array{title: string, summary: string, causes: array<int, string>, actions: array<int, string>, admin: string, retry: bool, login: bool, icon: string}>
     */
    public const CATALOG = [
        400 => [
            'title' => 'Demande incorrecte',
            'summary' => 'Le serveur n’a pas pu comprendre la demande envoyée par votre navigateur.',
            'causes' => ['Un lien copié de façon incomplète ou modifié.', 'Un formulaire envoyé avec des données inattendues.'],
            'actions' => ['Revenez à la page précédente et recommencez.', 'Ouvrez la page depuis le menu plutôt que depuis un ancien lien.'],
            'admin' => 'Vérifier le lien ou le formulaire à l’origine de la demande et les données qu’il envoie.',
            'retry' => false, 'login' => false, 'icon' => 'fa-solid fa-circle-exclamation',
        ],
        401 => [
            'title' => 'Connexion requise',
            'summary' => 'Cette page est réservée aux membres connectés.',
            'causes' => ['Vous n’êtes pas connecté ou votre session s’est terminée.'],
            'actions' => ['Connectez-vous puis revenez sur la page.'],
            'admin' => 'Si le membre est bien connecté : vérifier la durée et le domaine des cookies de session (réglages de session du serveur).',
            'retry' => false, 'login' => true, 'icon' => 'fa-solid fa-lock',
        ],
        403 => [
            'title' => 'Accès refusé',
            'summary' => 'Vous êtes connecté, mais votre compte n’a pas le droit d’ouvrir cette page ou d’effectuer cette action.',
            'causes' => ['Votre poste ou votre rôle ne donne pas accès à cette fonctionnalité.', 'Votre mandat est terminé ou votre adhésion n’est pas encore validée.', 'La fonctionnalité est réservée aux administrateurs.'],
            'actions' => ['Vérifiez que vous utilisez le bon compte.', 'Si vous pensez devoir y avoir accès, transmettez le rapport ci-dessous à l’administrateur.'],
            'admin' => 'Ouvrir la fiche du membre dans l’administration et contrôler son poste, son mandat actif et les modules autorisés. Accorder ou retirer l’accès au besoin.',
            'retry' => false, 'login' => false, 'icon' => 'fa-solid fa-ban',
        ],
        404 => [
            'title' => 'Page introuvable',
            'summary' => 'La page demandée n’existe pas ou n’existe plus.',
            'causes' => ['Le lien est incorrect, ancien ou incomplet.', 'Le contenu a été supprimé, déplacé ou n’est plus publié.', 'Le module concerné n’est pas encore ouvert aux membres.'],
            'actions' => ['Vérifiez l’adresse saisie.', 'Repartez de l’accueil et utilisez le menu ou la recherche.'],
            'admin' => 'Identifier la page qui contient ce lien et le corriger. Vérifier que le contenu existe, est publié et que son module est activé.',
            'retry' => false, 'login' => false, 'icon' => 'fa-solid fa-magnifying-glass',
        ],
        405 => [
            'title' => 'Action impossible depuis cette page',
            'summary' => 'La page existe, mais pas pour l’action que votre navigateur a tentée.',
            'causes' => ['Une page d’action (envoi de formulaire) a été ouverte directement par son adresse.', 'La page a été actualisée après un envoi de formulaire.'],
            'actions' => ['Revenez à la page précédente puis recommencez depuis le bouton prévu.'],
            'admin' => 'Vérifier que le bouton ou le formulaire utilise la bonne méthode (POST, PUT, DELETE) et la bonne adresse.',
            'retry' => false, 'login' => false, 'icon' => 'fa-solid fa-hand',
        ],
        408 => [
            'title' => 'Délai dépassé',
            'summary' => 'La demande a mis trop de temps à parvenir au serveur.',
            'causes' => ['Connexion internet lente ou interrompue.', 'Envoi d’un fichier volumineux.'],
            'actions' => ['Vérifiez votre connexion puis réessayez.', 'Pour un envoi de fichier, choisissez une image plus légère.'],
            'admin' => 'Vérifier les délais d’attente du serveur web et la taille maximale des envois.',
            'retry' => true, 'login' => false, 'icon' => 'fa-regular fa-clock',
        ],
        413 => [
            'title' => 'Fichier trop volumineux',
            'summary' => 'Le fichier que vous avez envoyé dépasse la taille autorisée.',
            'causes' => ['Photo ou document trop lourd.'],
            'actions' => ['Réduisez la taille du fichier (photo plus petite, PDF compressé) puis recommencez.'],
            'admin' => 'Comparer la taille du fichier aux limites du serveur (upload_max_filesize, post_max_size, client_max_body_size) et les relever si nécessaire.',
            'retry' => false, 'login' => false, 'icon' => 'fa-solid fa-file-arrow-up',
        ],
        419 => [
            'title' => 'Session expirée',
            'summary' => 'Pour votre sécurité, la page est restée ouverte trop longtemps et son formulaire n’est plus valide.',
            'causes' => ['La page est restée ouverte longtemps avant l’envoi du formulaire.', 'Vous vous êtes déconnecté dans un autre onglet.', 'Les cookies sont bloqués par le navigateur.'],
            'actions' => ['Rechargez la page, puis renvoyez le formulaire.', 'Si le problème revient, autorisez les cookies pour ce site.'],
            'admin' => 'Si cela touche plusieurs membres : contrôler la durée de session, le domaine des cookies et le bon fonctionnement du stockage des sessions.',
            'retry' => true, 'login' => false, 'icon' => 'fa-regular fa-hourglass-half',
        ],
        429 => [
            'title' => 'Trop de tentatives',
            'summary' => 'Vous avez fait beaucoup de demandes en peu de temps : l’accès est suspendu quelques instants.',
            'causes' => ['Actions répétées trop rapidement (connexion, envoi de formulaire, commande…).'],
            'actions' => ['Patientez une à deux minutes puis réessayez.'],
            'admin' => 'Si un membre est bloqué à tort : vérifier la limite de requêtes de la route concernée (throttle).',
            'retry' => true, 'login' => false, 'icon' => 'fa-solid fa-gauge-high',
        ],
        500 => [
            'title' => 'Erreur du serveur',
            'summary' => 'Un problème technique est survenu de notre côté. Ce n’est pas de votre faute.',
            'causes' => ['Une erreur inattendue dans l’application.', 'Un service momentanément indisponible (base de données, messagerie).'],
            'actions' => ['Réessayez dans quelques instants.', 'Si l’erreur persiste, transmettez le rapport ci-dessous à l’administrateur : sa référence permet de retrouver le détail technique.'],
            'admin' => 'Rechercher la référence dans storage/logs/laravel.log : l’entrée contient la cause exacte et le fichier concerné. Corriger puis prévenir le membre.',
            'retry' => true, 'login' => false, 'icon' => 'fa-solid fa-screwdriver-wrench',
        ],
        502 => [
            'title' => 'Serveur momentanément injoignable',
            'summary' => 'Le serveur de l’application n’a pas répondu correctement.',
            'causes' => ['Redémarrage ou surcharge du serveur.'],
            'actions' => ['Patientez quelques minutes puis rechargez la page.'],
            'admin' => 'Contrôler que PHP-FPM / le serveur d’application tourne et consulter les logs du serveur web.',
            'retry' => true, 'login' => false, 'icon' => 'fa-solid fa-server',
        ],
        503 => [
            'title' => 'Service momentanément indisponible',
            'summary' => 'Le site est en maintenance ou temporairement surchargé.',
            'causes' => ['Mise à jour en cours.', 'Forte affluence.'],
            'actions' => ['Revenez dans quelques minutes.'],
            'admin' => 'Vérifier si le mode maintenance est actif (php artisan up) et l’état du serveur.',
            'retry' => true, 'login' => false, 'icon' => 'fa-solid fa-hammer',
        ],
        504 => [
            'title' => 'Le serveur met trop de temps à répondre',
            'summary' => 'La demande a pris trop de temps à être traitée.',
            'causes' => ['Traitement long ou serveur surchargé.'],
            'actions' => ['Patientez quelques minutes puis réessayez.'],
            'admin' => 'Contrôler les délais d’exécution PHP et du serveur web, ainsi que la charge du serveur.',
            'retry' => true, 'login' => false, 'icon' => 'fa-regular fa-clock',
        ],
    ];

    /** Référence d'incident, identique pendant toute la requête (page, log et rapport). Format ERR-251031-A1B2C3. */
    public static function reference(Request $request): string
    {
        if (! is_string($request->attributes->get(self::ATTRIBUTE))) {
            $request->attributes->set(self::ATTRIBUTE, 'ERR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)));
        }

        return $request->attributes->get(self::ATTRIBUTE);
    }

    /** Contexte ajouté aux logs des exceptions rapportées : relie l'incident vu par le membre à son entrée de log. */
    public static function logContext(?Request $request = null): array
    {
        $request ??= request();

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return [];
        }

        return ['error_reference' => self::reference($request), 'error_path' => self::path($request)];
    }

    /** Chemin demandé, sans paramètres d'URL. */
    public static function path(Request $request): string
    {
        return '/'.ltrim($request->path(), '/');
    }

    /**
     * Données de la page d'erreur.
     *
     * @return array<string, mixed>
     */
    public static function build(Request $request, int $code, ?Throwable $exception = null): array
    {
        $info = self::CATALOG[$code] ?? self::fallback($code);
        $reference = self::reference($request);
        $path = self::path($request);
        $user = self::user();
        $contact = self::contactEmail();
        $when = now()->format('d/m/Y H:i:s');

        $lines = [
            'Signalement d’erreur — '.config('app.name'),
            'Référence : '.$reference,
            'Erreur : '.$code.' — '.$info['title'],
            'Page : '.$path.' ('.$request->method().')',
            'Date : '.$when.' ('.config('app.timezone').')',
            'Compte : '.($user ? $user['name'].' (n° '.$user['id'].')' : 'visiteur non connecté'),
            'Navigateur : '.Str::limit(trim((string) $request->userAgent()), 140, '…'),
            '',
            'Piste de résolution : '.$info['admin'],
        ];

        if (in_array($code, self::LOGGED_CODES, true)) {
            self::logIncident($request, $code, $reference, $path, $user['id'] ?? null);
        }

        return [
            'code' => $code,
            'info' => $info,
            'reference' => $reference,
            'path' => $path,
            'when' => $when,
            'user' => $user,
            'contact' => $contact,
            'report' => implode("\n", $lines),
            'debugMessage' => config('app.debug') && $exception ? $exception->getMessage() : null,
        ];
    }

    /** @return array{title: string, summary: string, causes: array<int, string>, actions: array<int, string>, admin: string, retry: bool, login: bool, icon: string} */
    private static function fallback(int $code): array
    {
        return $code >= 500
            ? [...self::CATALOG[500], 'title' => 'Erreur du serveur ('.$code.')']
            : [...self::CATALOG[400], 'title' => 'Demande impossible ('.$code.')'];
    }

    /** @return array{id: int, name: string}|null  Une session indisponible ne doit jamais aggraver l'erreur. */
    private static function user(): ?array
    {
        try {
            $user = auth()->user();

            return $user ? ['id' => (int) $user->getAuthIdentifier(), 'name' => (string) $user->name] : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Adresse de contact saisie dans l'administration (Site public > Contact), si elle est valide. */
    private static function contactEmail(): ?string
    {
        try {
            $email = app(SiteSettings::class)->stored('contact_email');

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function logIncident(Request $request, int $code, string $reference, string $path, ?int $userId): void
    {
        try {
            logger()->notice('Page d’erreur affichée', [
                'error_reference' => $reference, 'status' => $code, 'method' => $request->method(), 'path' => $path, 'userId' => $userId,
            ]);
        } catch (Throwable) {
            // Les logs ne doivent jamais empêcher l'affichage de la page.
        }
    }
}
