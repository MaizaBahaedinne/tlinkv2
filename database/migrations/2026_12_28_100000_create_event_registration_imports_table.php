<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscrits d'un événement connus seulement par leur nom, prénom et téléphone (liste importée par l'équipe).
 * Chaque participant retrouve sa fiche grâce à son numéro, la complète (photo, identité, parcours, paiement)
 * et son compte, son profil et son inscription sont alors créés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('release_id')->nullable()->constrained('event_releases')->nullOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('phone', 30);
            // 8 derniers chiffres du numéro : clé de recherche indépendante de la mise en forme (espaces, +216…).
            $table->string('phone_key', 8);
            $table->string('status', 12)->default('pending');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registration_id')->nullable()->constrained('event_registrations')->nullOnDelete();
            $table->json('declared')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'phone_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_imports');
    }
};
