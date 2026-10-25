<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            // Droits choisis poste par poste (niveau « custom »). Les postes existants gardent leur niveau actuel.
            $table->json('permissions')->nullable()->after('access_level');
        });
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropColumn('permissions');
        });
    }
};
