<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Habilitations CRUD par module sur chaque poste du modèle d'équipe d'un type de club.
 * Null : le poste garde son niveau d'accès prédéfini (comportement précédent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('club_template_slots') && ! Schema::hasColumn('club_template_slots', 'module_permissions')) {
            Schema::table('club_template_slots', function (Blueprint $table): void {
                $table->json('module_permissions')->nullable()->after('access_level');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('club_template_slots', 'module_permissions')) {
            Schema::table('club_template_slots', function (Blueprint $table): void {
                $table->dropColumn('module_permissions');
            });
        }
    }
};
