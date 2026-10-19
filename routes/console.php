<?php

use App\Support\LegacyClubImporter;
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
