<?php

namespace App\Console\Commands;

use App\Models\PushDevice;
use App\Models\User;
use App\Support\Push\Fcm;
use Illuminate\Console\Command;

class PushTest extends Command
{
    protected $signature = 'push:test {email? : Compte à notifier (sinon : état général des appareils)} {--channel=tlink : Canal et son : tlink ou messenger}';

    protected $description = 'Envoie une notification push de test aux appareils d\'un compte et affiche la réponse de Firebase';

    public function handle(): int
    {
        $this->line('Firebase configuré : '.(Fcm::enabled() ? 'oui' : 'NON (vérifier FCM_CREDENTIALS)'));
        $this->line('Appareils enregistrés : '.PushDevice::query()->count());

        $email = $this->argument('email');

        if (! $email) {
            return self::SUCCESS;
        }

        $user = User::query()->where('email', strtolower(trim((string) $email)))->first();

        if (! $user) {
            $this->error('Aucun compte avec cet e-mail.');

            return self::FAILURE;
        }

        $devices = PushDevice::query()->where('user_id', $user->id)->get();
        $this->line('Appareils de '.$user->name.' : '.$devices->count());

        if ($devices->isEmpty()) {
            $this->warn('Aucun appareil : ouvrir l\'application, se connecter et accepter les notifications.');

            return self::FAILURE;
        }

        $channel = in_array($this->option('channel'), ['tlink', 'messenger'], true) ? (string) $this->option('channel') : 'tlink';
        $results = Fcm::send($devices, 'Test T.Link', 'Si tu lis ceci, les notifications push fonctionnent.', ['url' => url('/notifications')], 'push-test', $channel);

        if ($results === []) {
            $this->error('Envoi impossible : jeton d\'accès Firebase refusé (voir storage/logs/laravel.log).');

            return self::FAILURE;
        }

        foreach ($results as $result) {
            $result['ok']
                ? $this->info('Appareil #'.$result['device'].' : envoyé (HTTP '.$result['status'].')')
                : $this->error('Appareil #'.$result['device'].' : refusé (HTTP '.$result['status'].') '.$result['error']);
        }

        return collect($results)->every(fn (array $r): bool => $r['ok']) ? self::SUCCESS : self::FAILURE;
    }
}
