<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('org_nodes', 'module_permissions')) {
            Schema::table('org_nodes', function (Blueprint $table): void {
                // Matrice CRUD du poste : { "feed.posts": ["create", "read"], ... }
                $table->json('module_permissions')->nullable()->after('permissions');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('org_nodes', 'module_permissions')) {
            Schema::table('org_nodes', function (Blueprint $table): void {
                $table->dropColumn('module_permissions');
            });
        }
    }
};
