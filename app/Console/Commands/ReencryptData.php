<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Après une copie de données d'une instance vers une autre (clés APP_KEY différentes), le CIN et les pièces d'identité,
 * chiffrés avec l'ancienne clé, deviennent illisibles : cette commande les rechiffre avec la clé actuelle.
 */
class ReencryptData extends Command
{
    protected $signature = 'data:reencrypt {--dry-run : Compte les valeurs sans rien écrire}';

    protected $description = 'Rechiffre CIN et pièces d\'identité depuis une ancienne APP_KEY (variable OLD_APP_KEY) vers la clé actuelle';

    /** @var array{converted: int, already: int, failed: int} */
    private array $stats = ['converted' => 0, 'already' => 0, 'failed' => 0];

    private Encrypter $old;

    public function handle(): int
    {
        $this->stats = ['converted' => 0, 'already' => 0, 'failed' => 0];
        $raw = (string) getenv('OLD_APP_KEY');

        if ($raw === '') {
            $this->error('Variable OLD_APP_KEY absente (APP_KEY de l\'ancienne instance, avec son préfixe base64:).');

            return self::FAILURE;
        }

        $key = str_starts_with($raw, 'base64:') ? base64_decode(substr($raw, 7), true) : $raw;

        if (! is_string($key) || ! Encrypter::supported($key, config('app.cipher'))) {
            $this->error('Clé OLD_APP_KEY invalide pour le chiffrement '.config('app.cipher').'.');

            return self::FAILURE;
        }

        $this->old = new Encrypter($key, config('app.cipher'));
        $dry = (bool) $this->option('dry-run');

        $columns = ['profiles' => ['cin'], 'identity_verifications' => ['cin_number', 'card_issued_on', 'card_issued_place']];

        foreach ($columns as $table => $names) {
            DB::table($table)->orderBy('id')->each(function (object $row) use ($table, $names, $dry): void {
                $update = [];

                foreach ($names as $name) {
                    $converted = $this->convert($row->{$name});

                    if ($converted !== $row->{$name}) {
                        $update[$name] = $converted;
                    }
                }

                if ($update !== [] && ! $dry) {
                    DB::table($table)->where('id', $row->id)->update($update);
                }
            });
        }

        // Pièces d'identité : fichiers du disque privé, chiffrés avec la même clé.
        $disk = Storage::disk('local');

        foreach (DB::table('identity_verifications')->get(['id_front_path', 'id_back_path', 'selfie_path']) as $row) {
            foreach (['id_front_path', 'id_back_path', 'selfie_path'] as $column) {
                $path = $row->{$column};

                if (! $path || ! $disk->exists($path)) {
                    continue;
                }

                $content = (string) $disk->get($path);
                $converted = $this->convert($content);

                if (! $dry && $converted !== $content) {
                    $disk->put($path, $converted);
                }
            }
        }

        $this->info(($dry ? '[simulation] ' : '').'Rechiffrées : '.$this->stats['converted'].', déjà à jour : '.$this->stats['already'].', illisibles : '.$this->stats['failed']);

        return $this->stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Ancienne clé -> clé actuelle ; une valeur déjà lisible avec la clé actuelle est laissée telle quelle. */
    private function convert(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            $plain = $this->old->decryptString($value);
        } catch (DecryptException) {
            try {
                app('encrypter')->decryptString($value);
                $this->stats['already']++;
            } catch (DecryptException) {
                $this->stats['failed']++;
            }

            return $value;
        }

        $this->stats['converted']++;

        return app('encrypter')->encryptString($plain);
    }
}
