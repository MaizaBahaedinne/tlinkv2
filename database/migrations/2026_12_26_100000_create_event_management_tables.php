<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion complète d'un événement : releases (tarifs), programme, intervenants, sponsors et partenaires par niveau,
 * paiements avec preuve et validateur, équipe d'organisation (OC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->foreignId('release_id')->nullable()->constrained('event_releases')->nullOnDelete();
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->string('payment_status', 12)->default('free');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->json('payment_methods')->nullable();
        });

        Schema::create('event_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('type', 30)->default('other');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('venue_mode', 12)->default('event');
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('room')->nullable();
            $table->boolean('requires_registration')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_session_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('registered');
            $table->timestamps();
            $table->unique(['session_id', 'user_id']);
        });

        Schema::create('event_speakers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20)->default('speaker');
            $table->string('origin', 12)->default('external');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('organization')->nullable();
            $table->string('job_title')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('website_url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_session_speaker', function (Blueprint $table): void {
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained('event_speakers')->cascadeOnDelete();
            $table->primary(['session_id', 'speaker_id']);
        });

        Schema::create('event_sponsor_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 12)->default('sponsor');
            $table->unsignedInteger('rank')->default(1);
            $table->timestamps();
        });

        Schema::create('event_sponsors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('event_sponsor_levels')->cascadeOnDelete();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('website_url', 500)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('volunteer');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('event_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->string('method', 12);
            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status', 12)->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payments');
        Schema::dropIfExists('event_team_members');
        Schema::dropIfExists('event_sponsors');
        Schema::dropIfExists('event_sponsor_levels');
        Schema::dropIfExists('event_session_speaker');
        Schema::dropIfExists('event_speakers');
        Schema::dropIfExists('event_session_registrations');
        Schema::dropIfExists('event_sessions');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('payment_methods');
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('release_id');
            $table->dropColumn(['amount_due', 'payment_status']);
        });

        Schema::dropIfExists('event_releases');
    }
};
