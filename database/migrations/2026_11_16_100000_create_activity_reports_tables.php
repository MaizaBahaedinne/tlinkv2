<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compte rendu de clôture d'une activité : rédigé par le club, validé par son président, puis évalué par le comité.
        Schema::create('activity_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->unique()->constrained('club_activities')->cascadeOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->text('summary')->nullable();
            $table->text('hr_summary')->nullable();
            $table->decimal('budget_planned', 12, 3)->nullable();
            $table->decimal('budget_spent', 12, 3)->nullable();
            $table->decimal('budget_income', 12, 3)->nullable();
            $table->text('budget_notes')->nullable();
            $table->text('president_comment')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Partie RH : implication de chaque membre ayant participé à l'activité.
        Schema::create('activity_report_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 120)->nullable();
            $table->unsignedTinyInteger('implication');
            $table->string('comment', 400)->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'user_id']);
        });

        // Partie marketing : affiche, preuves (fichiers) et retombées (publications, TV, radio, presse).
        Schema::create('activity_report_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('label', 160)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('path')->nullable();
            $table->timestamps();

            $table->index(['report_id', 'kind']);
        });

        // Partie événements et sponsoring : partenaires de l'activité et nature de leur soutien.
        Schema::create('activity_report_partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('activity_reports')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('nature', 20);
            $table->decimal('amount', 12, 3)->nullable();
            $table->string('description', 400)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_report_partners');
        Schema::dropIfExists('activity_report_items');
        Schema::dropIfExists('activity_report_members');
        Schema::dropIfExists('activity_reports');
    }
};
