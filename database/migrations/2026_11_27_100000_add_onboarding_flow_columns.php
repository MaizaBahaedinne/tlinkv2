<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parcours d'intégration bloquant (e-mail, info session, ancien/nouveau, profil, poste du mandat en cours) :
 * - `users.onboarding_done_at` : parcours terminé ou dispensé. Les comptes existants sont dispensés pour ne pas être bloqués ;
 * - `memberships.position_id` : poste visé par une candidature (null pour une simple adhésion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarding_done_at')->nullable()->after('history_onboarding_at');
        });

        Schema::table('memberships', function (Blueprint $table): void {
            $table->unsignedBigInteger('position_id')->nullable()->after('node_id')->index();
        });

        DB::table('users')->update(['onboarding_done_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropColumn('position_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('onboarding_done_at');
        });
    }
};
