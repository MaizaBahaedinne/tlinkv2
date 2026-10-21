<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le bloc de l'organigramme porte desormais son perimetre et, pour un club, ses propres informations
 * (jusque-la dans les tables clubs / institutions). Execute avant le remplacement des roles : le code
 * de l'organigramme en a besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->string('scope', 30)->nullable()->after('category');
            $table->string('status', 20)->default('active')->after('scope');
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('status');
            $table->string('email')->nullable()->after('legacy_id');
            $table->string('facebook_url', 500)->nullable()->after('email');
            $table->date('founded_on')->nullable()->after('facebook_url');
            $table->boolean('charter_signed')->default(false)->after('founded_on');
            $table->string('institution_name')->nullable()->after('charter_signed');
            $table->string('institution_type', 20)->nullable()->after('institution_name');
        });
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn(['scope', 'status', 'legacy_id', 'email', 'facebook_url', 'founded_on', 'charter_signed', 'institution_name', 'institution_type']);
        });
    }
};
