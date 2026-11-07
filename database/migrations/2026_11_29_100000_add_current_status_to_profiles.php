<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Statut actuel du membre (élève, étudiant, salarié, chef d'entreprise, diaspora…), demandé pendant l'intégration.
        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('status_kind', 30)->nullable();
            $table->string('status_level', 40)->nullable();
            $table->string('status_country', 100)->nullable();
            $table->foreignId('status_company_id')->nullable()->constrained('companies')->nullOnDelete();
        });

        // Étiquette « membre Tunivisions » : la société est dirigée par un membre du réseau.
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('tunivisions_member')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('tunivisions_member');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_company_id');
            $table->dropColumn(['status_kind', 'status_level', 'status_country']);
        });
    }
};
