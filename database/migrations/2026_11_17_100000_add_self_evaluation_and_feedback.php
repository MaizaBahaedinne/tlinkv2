<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Auto-évaluation du club : note et justification par critère du barème, points forts et axes d'amélioration.
        Schema::table('activity_reports', function (Blueprint $table): void {
            $table->json('self_scores')->nullable()->after('hr_summary');
            $table->text('self_strengths')->nullable()->after('self_scores');
            $table->text('self_improvements')->nullable()->after('self_strengths');
        });

        // Avis du participant : donné en scannant le QR code dans l'application, il valide aussi sa participation.
        Schema::table('activity_registrations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('feedback_overall')->nullable()->after('checked_in_at');
            $table->unsignedTinyInteger('feedback_organization')->nullable()->after('feedback_overall');
            $table->unsignedTinyInteger('feedback_content')->nullable()->after('feedback_organization');
            $table->text('feedback_comment')->nullable()->after('feedback_content');
            $table->dateTime('feedback_at')->nullable()->after('feedback_comment');
        });
    }

    public function down(): void
    {
        Schema::table('activity_registrations', function (Blueprint $table): void {
            $table->dropColumn(['feedback_overall', 'feedback_organization', 'feedback_content', 'feedback_comment', 'feedback_at']);
        });

        Schema::table('activity_reports', function (Blueprint $table): void {
            $table->dropColumn(['self_scores', 'self_strengths', 'self_improvements']);
        });
    }
};
