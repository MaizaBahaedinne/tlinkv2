<?php

use App\Support\Gamification\GamificationEngine;
use App\Support\LegacyClubImporter;
use App\Support\Legacy\LegacyUserImporter;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\OrgChart\OrgChartLinker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stories:purge', function () {
    $this->info(\App\Support\Stories::purgeExpired().' story(ies) expirée(s) supprimée(s).');
})->purpose('Supprime les stories de plus de 24 h (lignes et fichiers)');

\Illuminate\Support\Facades\Schedule::command('stories:purge')->hourly();

Artisan::command('cards:sync', function () {
    $result = \App\Support\Cards\MemberCards::sync();

    $this->info($result['issued'].' carte(s) émise(s), '.$result['expired'].' carte(s) expirée(s).');
})->purpose('Émet les cartes de membre du mandat en cours (une par adhésion validée) et fait expirer les autres');

\Illuminate\Support\Facades\Schedule::command('cards:sync')->dailyAt('03:00');

Artisan::command('gamification:sync', function () {
    $result = GamificationEngine::refreshEveryone();

    $this->info($result['users'].' membre(s) recalculé(s), '.$result['awarded'].' badge(s) décerné(s).');
})->purpose('Recalcule les missions de tous les membres et décerne les badges manquants');

Artisan::command('legacy:import-clubs {path? : Chemin du dump SQL legacy}', function (?string $path = null) {
    $dumpPath = $path ?? collect([
        base_path('database/legacy/tbl_club.sql'),
        base_path('database/legacy/TlinkDB.sql'),
        '/Users/mac/Downloads/TlinkDB.sql',
    ])->first(fn (string $candidate) => is_file($candidate), base_path('database/legacy/tbl_club.sql'));

    $stats = app(LegacyClubImporter::class)->import($dumpPath);

    $this->info('Import legacy termine.');
    $this->line('Branches synchronisees: '.$stats['branches']);
    $this->line('Clubs importes: '.$stats['clubs'].' ('.$stats['created'].' crees, '.$stats['updated'].' mis a jour)');
    $this->line('Lignes ignorees: '.$stats['skipped']);
    if ($stats['no_governorate'] !== []) {
        $this->warn('Clubs sans gouvernorat reconnu ('.count($stats['no_governorate']).') : '.implode(' ; ', $stats['no_governorate']));
    }
})->purpose('Importe tbl_club depuis un dump SQL legacy');

Artisan::command('legacy:import-users {path? : Chemin du dump SQL legacy} {--dry-run : Simule l\'import sans rien ecrire} {--report= : Chemin du rapport CSV}', function (?string $path = null) {
    $dumpPath = $path ?? collect([
        base_path('database/legacy/tbl_users.sql'),
        base_path('database/legacy/TlinkDB.sql'),
        '/Users/mac/Downloads/TlinkDB.sql',
    ])->first(fn (string $candidate) => is_file($candidate), base_path('database/legacy/tbl_users.sql'));

    $dryRun = (bool) $this->option('dry-run');
    $result = app(LegacyUserImporter::class)->import($dumpPath, $dryRun);
    $stats = $result['stats'];

    $this->info($dryRun ? 'SIMULATION : aucune donnee ecrite.' : 'Import des utilisateurs termine.');
    $this->line('Lignes lues: '.$stats['rows'].' -> comptes: '.$stats['users'].' ('.$stats['merged_rows'].' doublons d\'e-mail fusionnes)');
    $this->line('Crees: '.$stats['created'].' | deja presents: '.$stats['updated'].' (dont rattaches par e-mail: '.$stats['linked'].')');
    $this->line('Comptes inactifs (exclus/demissionnaires/supprimes): '.$stats['inactive']);
    $this->line('Adhesions: '.$stats['memberships'].' | clubs introuvables: '.$stats['skipped_clubs']);
    $this->line('E-mails corriges: '.$stats['emails_repaired'].' | e-mails de remplacement (a corriger): '.$stats['emails_placeholder']);
    $this->line('Avatars retrouves: '.$stats['avatars']);

    $reportPath = $this->option('report') ?: storage_path('app/legacy-users-report.csv');
    $handle = fopen($reportPath, 'wb');
    fputcsv($handle, ['legacy_id', 'probleme', 'detail'], ';');
    foreach ($result['report'] as [$legacyId, $issue, $detail]) {
        // Neutralise les formules Excel dans les valeurs importees.
        $detail = preg_match('/^[=+\-@\t\r]/', $detail) ? "'".$detail : $detail;
        fputcsv($handle, [$legacyId, $issue, $detail], ';');
    }
    fclose($handle);
    $this->line('Rapport (donnees personnelles, ne pas publier): '.$reportPath);
})->purpose('Migre tbl_users depuis un dump SQL legacy (utilisateurs, profils, adhesions)');

Artisan::command('orgchart:sync', function () {
    $installer = app(OrgChartInstaller::class);

    if (! $installer->isInstalled()) {
        $installer->install();
        $this->info('Organigramme installe.');
    }

    $installer->restructure();
    $stats = app(OrgChartLinker::class)->sync();

    $this->info('Organigramme synchronise : perimetres des blocs et rangement des clubs.');
    $this->line('Blocs systeme relies a un perimetre: '.$stats['linked']);
    $this->line('Clubs ranges ou mis a jour: '.$stats['updated']);
})->purpose('Aligne les perimetres et le rangement des clubs de l\'organigramme');

Artisan::command('onboarding:waive {email : Adresse e-mail du compte} {--verify-email : Confirme aussi l\'adresse e-mail}', function (string $email) {
    $user = \App\Models\User::query()->where('email', mb_strtolower(trim($email)))->first();

    if (! $user) {
        $this->error('Compte introuvable.');

        return self::FAILURE;
    }

    $user->forceFill(array_filter([
        'onboarding_done_at' => now(),
        'email_verified_at' => $this->option('verify-email') && ! $user->email_verified_at ? now() : null,
    ]))->save();

    $this->info('Parcours d\'intégration levé pour '.$user->email.'.');
})->purpose('Dispense un compte du parcours d\'intégration (dépannage : e-mail non reçu, validation bloquée)');

Artisan::command('onboarding:restart {email? : Adresse e-mail du compte} {--all : Tous les comptes} {--keep-email : Conserve la vérification de l\'e-mail}', function (?string $email = null) {
    // Le parcours est calculé d'après l'état réel du compte : remettre `onboarding_done_at` à NULL ne suffit pas, une étape déjà
    // franchie (e-mail vérifié, info session, ancien/nouveau, profil, adhésion approuvée) est revalidée aussitôt.
    // La dernière étape (adhésion/poste du mandat en cours) reste franchie tant que l'adhésion approuvée ou le poste existent.
    $query = \App\Models\User::query();

    if ($this->option('all')) {
        if (! $this->confirm('Remettre TOUS les comptes dans le parcours d\'intégration ?'.($this->option('keep-email') ? '' : ' Chacun devra revérifier son e-mail : MAIL_* doit être configuré.'))) {
            return self::FAILURE;
        }
    } elseif ($email !== null) {
        $query->where('email', mb_strtolower(trim($email)));
    } else {
        $this->error('Indiquez un e-mail ou --all.');

        return self::FAILURE;
    }

    $count = \App\Support\Onboarding::restart($query, (bool) $this->option('keep-email'));

    if ($count === 0) {
        $this->error('Compte introuvable.');

        return self::FAILURE;
    }

    $this->info($count.' compte(s) remis dans le parcours d\'intégration.');
})->purpose('Remet un ou tous les comptes au début du parcours d\'intégration (test, ré-imposition)');

Artisan::command('admin:create {email : Adresse e-mail du compte} {--name= : Nom complet (création uniquement)} {--password= : Mot de passe (généré si absent)}', function (string $email) {
    $email = mb_strtolower(trim($email));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Adresse e-mail invalide.');

        return self::FAILURE;
    }

    $installer = app(OrgChartInstaller::class);

    if (! \App\Models\OrgNode::query()->where('system_key', 'national-administrators')->exists()) {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => \Database\Seeders\GovernorateSeeder::class, '--force' => true]);
        $installer->install();
    }

    $user = \App\Models\User::query()->where('email', $email)->first();
    $password = null;

    if (! $user) {
        $password = (string) ($this->option('password') ?: \Illuminate\Support\Str::password(16, symbols: false));
        $user = \App\Models\User::query()->create([
            'name' => trim((string) ($this->option('name') ?: 'Administrateur')),
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
            'history_onboarding_at' => now(),
            'onboarding_done_at' => now(),
        ])->save();
    }

    $node = \App\Models\OrgNode::query()->where('system_key', 'national-administrators')->firstOrFail();
    $person = \App\Models\OrgPerson::query()->firstOrCreate(['user_id' => $user->id], ['full_name' => $user->name, 'contact_visible' => true]);
    \App\Models\OrgAssignment::query()->firstOrCreate(['node_id' => $node->id, 'person_id' => $person->id]);

    $this->info('Administrateur global : '.$email);

    if ($password !== null) {
        $this->line('Mot de passe : '.$password.' (à changer à la première connexion)');
    } else {
        $this->line('Compte existant : mot de passe inchangé.');
    }

    return self::SUCCESS;
})->purpose('Crée (ou promeut) un administrateur global de la plateforme');
