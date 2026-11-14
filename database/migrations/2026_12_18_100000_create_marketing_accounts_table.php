<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coffre des accès marketing partagés (Facebook, Instagram, e-mail) : un club ou la Fondation y dépose les accès
 * de ses comptes. Mot de passe et codes de secours sont chiffrés (casts « encrypted » du modèle).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_accounts')) {
            return;
        }

        Schema::create('marketing_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->nullable()->constrained('org_nodes')->nullOnDelete();
            $table->string('platform', 20);
            $table->string('name', 160);
            $table->string('login', 190);
            $table->text('password');
            $table->string('profile_url', 255)->nullable();
            $table->string('recovery_email', 190)->nullable();
            $table->string('recovery_phone', 40)->nullable();
            $table->string('two_factor', 20)->default('none');
            $table->text('recovery_codes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['club_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_accounts');
    }
};
