<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('org_nodes', 'extra_scopes')) {
            Schema::table('org_nodes', function (Blueprint $table): void {
                // Périmètres supplémentaires sur lesquels le poste agit, en plus de son périmètre principal : ["university", "alumni"]
                $table->json('extra_scopes')->nullable()->after('scope');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('org_nodes', 'extra_scopes')) {
            Schema::table('org_nodes', function (Blueprint $table): void {
                $table->dropColumn('extra_scopes');
            });
        }
    }
};
