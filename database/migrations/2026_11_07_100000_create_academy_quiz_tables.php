<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un quiz par module (module_id) ou un quiz final du cours (module_id nul).
        Schema::create('academy_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('academy_modules')->cascadeOnDelete();
            $table->string('title');
            $table->text('intro')->nullable();
            // Score minimal (en %) pour réussir, nombre de questions tirées au sort par tentative (nul = toutes).
            $table->unsignedTinyInteger('pass_percent')->default(70);
            $table->unsignedSmallInteger('questions_per_attempt')->nullable();
            // Un quiz obligatoire conditionne la fin du module (ou du cours) : badge et certificat.
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['course_id', 'module_id']);
        });

        Schema::create('academy_quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('academy_quizzes')->cascadeOnDelete();
            $table->text('text');
            $table->string('type', 10)->default('single');
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('academy_quiz_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_id')->constrained('academy_quiz_questions')->cascadeOnDelete();
            $table->string('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
        });

        // Le tirage (questions et réponses mélangées) est figé dans `snapshot` : la correction se fait sur ce qui a été affiché.
        Schema::create('academy_quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained('academy_quizzes')->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->unsignedTinyInteger('score_percent')->nullable();
            $table->json('snapshot');
            $table->json('answers')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'quiz_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_quiz_attempts');
        Schema::dropIfExists('academy_quiz_options');
        Schema::dropIfExists('academy_quiz_questions');
        Schema::dropIfExists('academy_quizzes');
    }
};
