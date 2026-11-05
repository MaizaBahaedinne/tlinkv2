<?php

use App\Support\OrgChart\OrgChartInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Structure type d'un club University / High School : Président + 4 départements,
 * chacun avec un VP, un Assistant et des membres. Les clubs existants sont mis au modèle.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Le code d'aujourd'hui lit `role_key`, colonne créée par une migration ultérieure (2026_10_26_100000) qui
        // aligne elle aussi les équipes de club : sur une base vierge, on laisse celle-ci s'en charger.
        if (Schema::hasTable('org_nodes') && Schema::hasColumn('org_nodes', 'role_key')) {
            app(OrgChartInstaller::class)->alignClubTeams();
        }
    }

    public function down(): void
    {
        // Les postes ajoutés peuvent déjà porter des personnes : rien n'est retiré.
    }
};
