<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comité d'organisation d'une activité : les membres du club (et des co-organisateurs) qui la portent, chacun avec son rôle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_committee_members')) {
            return;
        }

        Schema::create('activity_committee_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamps();

            $table->unique(['activity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_committee_members');
    }
};
