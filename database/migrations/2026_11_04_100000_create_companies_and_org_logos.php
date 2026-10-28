<?php

use App\Support\ModuleCatalog;
use App\Support\ProfileOrganizations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Base des entreprises citées dans les profils : une seule fiche par entreprise, avec son logo.
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 190)->unique();
            $table->string('logo_path')->nullable();
            $table->string('website_url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Les écoles sont les clubs (établissement) : leur logo décore les profils des membres.
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('institution_type');
        });

        ModuleCatalog::sync();

        // Rattache les profils existants : entreprise créée si elle n'existe pas, sinon sélectionnée ; école et club retrouvés par leur nom.
        (new ProfileOrganizations())->backfill();
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });

        Schema::dropIfExists('companies');
    }
};
