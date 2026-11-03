<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcours d'accueil après l'info session : ancien ou nouveau membre, et mandat d'arrivée dans le réseau
 * (les mandats précédents sont alors grisés dans « Mes adhésions »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('member_origin', 10)->nullable()->after('onboarding_completed_at');
            $table->unsignedBigInteger('joined_mandate_id')->nullable()->after('member_origin')->index();
            $table->timestamp('history_onboarding_at')->nullable()->after('joined_mandate_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['member_origin', 'joined_mandate_id', 'history_onboarding_at']);
        });
    }
};
