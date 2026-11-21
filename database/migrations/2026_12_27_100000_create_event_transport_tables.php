<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion du transport d'un événement : régions de départ, points de départ / collecte (GPS), bus avec circuit,
 * responsables de bus (présence et communication) et choix de transport des inscrits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_transport_regions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });

        Schema::create('event_transport_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('event_transport_regions')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('city', 120)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('event_buses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('plate', 40)->nullable();
            $table->unsignedInteger('capacity');
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Circuit d'un bus : points de départ / collecte dans l'ordre, jusqu'à l'adresse de l'événement.
        Schema::create('event_bus_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bus_id')->constrained('event_buses')->cascadeOnDelete();
            $table->foreignId('point_id')->constrained('event_transport_points')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->dateTime('departs_at')->nullable();
            $table->dateTime('arrives_at')->nullable();
            $table->timestamps();
            $table->unique(['bus_id', 'point_id']);
        });

        Schema::create('event_bus_managers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bus_id')->constrained('event_buses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('duty', 16)->default('both');
            $table->timestamps();
            $table->unique(['bus_id', 'user_id']);
        });

        Schema::create('event_transport_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('registration_id')->unique()->constrained('event_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('event_transport_regions')->nullOnDelete();
            $table->string('mode', 4)->default('own');
            $table->foreignId('bus_id')->nullable()->constrained('event_buses')->nullOnDelete();
            $table->foreignId('stop_id')->nullable()->constrained('event_bus_stops')->nullOnDelete();
            $table->string('boarding', 8)->default('pending');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_transport_bookings');
        Schema::dropIfExists('event_bus_managers');
        Schema::dropIfExists('event_bus_stops');
        Schema::dropIfExists('event_buses');
        Schema::dropIfExists('event_transport_points');
        Schema::dropIfExists('event_transport_regions');
    }
};
