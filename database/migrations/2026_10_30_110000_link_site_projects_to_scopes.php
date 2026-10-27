<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une page projet est le contenu (texte, chiffres, couverture) d'un périmètre de type « Projet ».
        if (! Schema::hasColumn('site_projects', 'scope')) {
            Schema::table('site_projects', function (Blueprint $table): void {
                $table->string('scope', 50)->nullable()->unique()->after('slug');
            });
        }

        // Les pages déjà créées gardent leur adresse comme périmètre.
        DB::table('site_projects')->whereNull('scope')->update(['scope' => DB::raw('slug')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('site_projects', 'scope')) {
            Schema::table('site_projects', function (Blueprint $table): void {
                $table->dropUnique(['scope']);
                $table->dropColumn('scope');
            });
        }
    }
};
