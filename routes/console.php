<?php

use App\Support\LegacyClubImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('legacy:import-clubs {path? : Chemin du dump SQL legacy}', function (?string $path = null) {
    $dumpPath = $path
        ?? (is_file('/Users/mac/Downloads/TlinkDB.sql') ? '/Users/mac/Downloads/TlinkDB.sql' : base_path('database/legacy/TlinkDB.sql'));

    $stats = app(LegacyClubImporter::class)->import($dumpPath);

    $this->info('Import legacy termine.');
    $this->line('Branches synchronisees: '.$stats['branches']);
    $this->line('Clubs importes: '.$stats['clubs']);
    $this->line('Lignes ignorees: '.$stats['skipped']);
})->purpose('Importe tbl_club depuis un dump SQL legacy');
