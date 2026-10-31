<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Défi thématique lancé par un bureau de chapitre ou de projet : sujet, calendrier, barèmes, comité.
        Schema::create('challenges', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('scope', 60)->nullable()->index();
            $table->json('invited_scopes')->nullable();
            $table->string('theme');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('evaluation_at');
            $table->dateTime('results_at');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Barème : critères notés, un jeu pour les activités dans la thématique et un autre pour les activités hors thématique.
        Schema::create('challenge_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);
            $table->string('label', 160);
            $table->string('description', 400)->nullable();
            $table->unsignedSmallInteger('max_points');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['challenge_id', 'kind']);
        });

        // Comité d'évaluation du défi.
        Schema::create('challenge_committee', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
        });

        // Activité d'un club dans le cadre d'un défi.
        Schema::create('club_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('org_nodes')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->string('audience', 20);
            $table->foreignId('department_id')->nullable()->constrained('org_nodes')->nullOnDelete();
            $table->boolean('in_theme')->default(true);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->dateTime('registration_ends_at')->nullable();
            $table->json('registration_fields')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('checkin_token', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['challenge_id', 'club_id']);
        });

        // Clubs co-organisateurs : l'invitation doit être acceptée par le club invité.
        Schema::create('activity_organizers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained('org_nodes')->cascadeOnDelete();
            $table->string('status', 12)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'club_id']);
        });

        Schema::create('activity_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('registered');
            $table->json('answers')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'user_id']);
        });

        // Note d'un membre du comité pour un critère d'une activité.
        Schema::create('activity_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('challenge_criteria')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('points', 6, 2);
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'criterion_id', 'evaluator_id']);
        });

        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_scores');
        Schema::dropIfExists('activity_registrations');
        Schema::dropIfExists('activity_organizers');
        Schema::dropIfExists('club_activities');
        Schema::dropIfExists('challenge_committee');
        Schema::dropIfExists('challenge_criteria');
        Schema::dropIfExists('challenges');
    }
};
