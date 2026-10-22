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
        if (Schema::hasTable('org_nodes')) {
            app(OrgChartInstaller::class)->alignClubTeams();
        }
    }

    public function down(): void
    {
        // Les postes ajoutés peuvent déjà porter des personnes : rien n'est retiré.
    }
};
