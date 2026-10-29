<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Annuaire des mentors : un membre y figure une seule fois ; `is_manager` désigne le manager mentor
        // qui reçoit les tickets, les qualifie et les affecte.
        Schema::create('mentoring_mentors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_manager')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('specialties', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Ticket remonté par un membre : demande, incident ou problème, à titre personnel ou pour son collectif.
        Schema::create('mentoring_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->nullable()->unique();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 12)->default('request');
            $table->string('audience', 12)->default('personal');
            $table->string('collective_type', 12)->nullable();
            $table->string('collective_name', 160)->nullable();
            $table->foreignId('node_id')->nullable()->constrained('org_nodes')->nullOnDelete();
            $table->string('title', 160);
            $table->text('description');
            $table->string('status', 12)->default('new');

            // Qualification par le manager mentor.
            $table->string('category', 20)->nullable();
            $table->string('priority', 10)->default('normal');
            $table->text('qualification_note')->nullable();
            $table->foreignId('qualified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('qualified_at')->nullable();

            // Affectation à un mentor.
            $table->foreignId('mentor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at')->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('requester_id');
            $table->index('mentor_id');
        });

        // Journal du ticket : commentaires publics, notes internes (équipe de mentors uniquement) et changements d'état.
        Schema::create('mentoring_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('mentoring_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12);
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'id']);
        });

        // Retour d'expérience rédigé à l'issue de l'accompagnement.
        Schema::create('mentoring_rex', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained('mentoring_tickets')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('context');
            $table->text('actions');
            $table->text('outcome');
            $table->text('lessons');
            $table->text('recommendations')->nullable();
            $table->timestamps();
        });

        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('mentoring_rex');
        Schema::dropIfExists('mentoring_events');
        Schema::dropIfExists('mentoring_tickets');
        Schema::dropIfExists('mentoring_mentors');
    }
};
