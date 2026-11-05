<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * État du déploiement affiché dans l'administration globale : version en ligne, migrations en attente, contrôles d'hygiène.
 * Aucune opération de déploiement n'est lancée depuis le web : le code, les migrations et les dépendances passent par le serveur.
 */
class Deployment
{
    /**
     * Version déployée : `.release.json` (écrit par le script de déploiement), sinon le dépôt git s'il est présent.
     *
     * @return array{commit: ?string, branch: ?string, deployed_at: ?string}
     */
    public static function release(): array
    {
        $info = ['commit' => null, 'branch' => null, 'deployed_at' => null];
        $file = base_path('.release.json');

        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);

            if (is_array($data)) {
                $commit = (string) ($data['commit'] ?? '');

                return [
                    'commit' => preg_match('/^[0-9a-f]{7,40}$/', $commit) ? $commit : null,
                    'branch' => isset($data['branch']) ? (string) $data['branch'] : null,
                    'deployed_at' => isset($data['deployed_at']) ? (string) $data['deployed_at'] : null,
                ];
            }
        }

        $head = base_path('.git/HEAD');

        if (is_file($head)) {
            $ref = trim((string) file_get_contents($head));

            if (str_starts_with($ref, 'ref: ')) {
                $name = substr($ref, 5);
                $path = base_path('.git/'.$name);
                $info['branch'] = basename($name);
                $info['commit'] = is_file($path) ? trim((string) file_get_contents($path)) : null;
            } else {
                $info['commit'] = $ref;
            }

            if ($info['commit'] !== null && ! preg_match('/^[0-9a-f]{7,40}$/', $info['commit'])) {
                $info['commit'] = null;
            }
        }

        return $info;
    }

    /** @return array<int, string> noms des migrations pas encore exécutées */
    public static function pendingMigrations(): array
    {
        $migrator = app('migrator');
        $repository = $migrator->getRepository();

        if (! $repository->repositoryExists()) {
            return [];
        }

        $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));

        return array_values(array_diff(array_keys($files), $repository->getRan()));
    }

    /** Contenu du fichier d'état de la maintenance (clé secrète de contournement comprise), ou null si le site est en ligne. */
    public static function maintenance(): ?array
    {
        $file = storage_path('framework/down');

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<int, array{label: string, ok: bool, detail: string}>
     */
    public static function checks(?array $pending = null): array
    {
        $pending ??= self::pendingMigrations();
        $isProduction = app()->environment('production');
        $storageLink = public_path('storage');

        return [
            ['label' => 'Environnement de production', 'ok' => $isProduction, 'detail' => 'APP_ENV = '.app()->environment()],
            ['label' => 'Mode debug désactivé', 'ok' => ! config('app.debug'), 'detail' => config('app.debug') ? 'APP_DEBUG est actif : les erreurs détaillées sont visibles.' : 'Les erreurs techniques ne sont pas affichées.'],
            ['label' => 'Adresse en HTTPS', 'ok' => str_starts_with((string) config('app.url'), 'https://'), 'detail' => (string) config('app.url')],
            ['label' => 'Cookie de session sécurisé', 'ok' => (bool) config('session.secure'), 'detail' => config('session.secure') ? 'SESSION_SECURE_COOKIE actif.' : 'SESSION_SECURE_COOKIE est inactif.'],
            ['label' => 'Migrations à jour', 'ok' => $pending === [], 'detail' => $pending === [] ? 'Aucune migration en attente.' : count($pending).' en attente.'],
            ['label' => 'Lien public/storage', 'ok' => is_link($storageLink) && is_dir($storageLink), 'detail' => is_link($storageLink) ? 'Actif.' : 'Absent : php artisan storage:link.'],
            ['label' => 'Envoi des e-mails', 'ok' => ! in_array(config('mail.default'), ['log', 'array'], true), 'detail' => 'MAIL_MAILER = '.config('mail.default')],
            ['label' => 'Table des versions', 'ok' => Schema::hasTable('app_releases'), 'detail' => Schema::hasTable('app_releases') ? 'Présente.' : 'Manquante.'],
        ];
    }
}
