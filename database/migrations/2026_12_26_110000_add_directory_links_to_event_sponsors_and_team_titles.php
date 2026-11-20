<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsors d'événement choisis dans le carnet de partenaires (sponsors) ou les partenaires du site,
 * et poste libre (texte) des membres de l'équipe d'organisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sponsors', function (Blueprint $table): void {
            $table->foreignId('directory_sponsor_id')->nullable()->constrained('sponsors')->nullOnDelete();
            $table->foreignId('directory_partner_id')->nullable()->constrained('partners')->nullOnDelete();
        });

        Schema::table('event_team_members', function (Blueprint $table): void {
            $table->string('job_title', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_team_members', function (Blueprint $table): void {
            $table->dropColumn('job_title');
        });

        Schema::table('event_sponsors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('directory_sponsor_id');
            $table->dropConstrainedForeignId('directory_partner_id');
        });
    }
};
