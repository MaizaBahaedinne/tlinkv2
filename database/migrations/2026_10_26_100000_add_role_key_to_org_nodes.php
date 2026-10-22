<?php

use App\Support\OrgChart\OrgChartInstaller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rôle d'un poste dans l'équipe type des clubs University / High School : les droits se définissent
 * une fois par rôle, pour tous les clubs. Les clubs sans équipe reçoivent l'équipe type.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('org_nodes', 'role_key')) {
            Schema::table('org_nodes', function (Blueprint $table): void {
                $table->string('role_key', 40)->nullable()->after('system_key')->index();
            });
        }

        app(OrgChartInstaller::class)->alignClubTeams();
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropIndex(['role_key']);
            $table->dropColumn('role_key');
        });
    }
};
