<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rattachement des blocs de l'organigramme a la structure de la plateforme.
        // Les blocs « club » suivent leur club ; les autres portent un lien explicite
        // que leurs sous-blocs heritent (calcule a la lecture).
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->foreignId('entity_id')->nullable()->after('governorate_id')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('entity_id')->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('institution_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('entity_id');
        });
    }
};
