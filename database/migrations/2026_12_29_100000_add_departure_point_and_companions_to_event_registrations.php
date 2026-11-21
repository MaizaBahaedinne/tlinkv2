<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'inscrit choisit seulement son point de départ (le bus est affecté ensuite par l'équipe transport)
 * et, pour les High School, peut déclarer des accompagnants (nom, prénom, lien familial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport_bookings', function (Blueprint $table): void {
            $table->foreignId('point_id')->nullable()->after('region_id')->constrained('event_transport_points')->nullOnDelete();
        });

        Schema::create('event_registration_companions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('relationship', 24);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_companions');

        Schema::table('event_transport_bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('point_id');
        });
    }
};
