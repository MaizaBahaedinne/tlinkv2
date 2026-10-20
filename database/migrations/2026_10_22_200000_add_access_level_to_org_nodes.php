<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            // Niveau d'acces du poste (remplace les anciens roles) : none | global_admin | entity_admin | manager | club_president
            $table->string('access_level', 20)->default('none')->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropColumn('access_level');
        });
    }
};
