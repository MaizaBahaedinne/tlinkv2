<?php

use App\Support\LegacyClubImporter;
use App\Support\Legacy\LegacyUserImporter;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\OrgChart\OrgChartLinker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

    $stats = app(OrgChartLinker::class)->sync();

    $this->info('Organigramme synchronise avec les entites, departements, institutions et clubs.');
    $this->line('Blocs systeme relies: '.$stats['linked']);
    $this->line('Blocs de clubs crees: '.$stats['created'].' | mis a jour: '.$stats['updated']);
    $this->line('Clubs sans branche dans l\'organigramme (ni University ni High School): '.$stats['skipped']);
})->purpose('Relie l\'organigramme aux entites, departements, institutions et clubs');
