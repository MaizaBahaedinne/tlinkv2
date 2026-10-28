<?php

use App\Models\Institution;
use App\Support\ModuleCatalog;
use App\Support\ProfileOrganizations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Établissements d'enseignement (lycées, universités, facultés, écoles) : une fiche par établissement, avec son logo.
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 190)->unique();
            $table->string('type', 20)->default('other');
            $table->foreignId('parent_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('website_url')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // Un club d'université ou de lycée est rattaché à son établissement.
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->foreignId('institution_id')->nullable()->after('institution_type')->constrained('institutions')->nullOnDelete();
        });

        ModuleCatalog::sync();

        // Reprend l'établissement saisi en texte sur chaque club, puis relie les profils aux fiches créées.
        Institution::backfillFromClubs();
        (new ProfileOrganizations())->backfill();
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('institution_id');
        });

        Schema::dropIfExists('institutions');
    }
};
