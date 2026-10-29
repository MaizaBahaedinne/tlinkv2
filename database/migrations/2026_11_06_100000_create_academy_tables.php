<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tunivisions Academy : cours > modules > leçons.
        Schema::create('academy_courses', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 190)->unique();
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('level', 16)->default('beginner');
            $table->string('instructor_name', 120)->nullable();
            // Prix en TND : NULL ou 0 = gratuit.
            $table->decimal('price', 8, 2)->nullable();
            $table->boolean('included_in_plans')->default(true);
            // Badge décerné à la fin du cours, et points bonus de fin de cours.
            $table->foreignId('badge_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('completion_points')->default(50);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        Schema::create('academy_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->foreignId('badge_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('academy_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->constrained('academy_modules')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('kind', 12)->default('text');
            $table->string('video_url')->nullable();
            $table->text('content')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedInteger('points')->default(10);
            // Leçon d'aperçu : visible sans avoir accès au cours.
            $table->boolean('is_preview')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('academy_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->string('source', 16)->default('free');
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('certificate_code', 24)->nullable()->unique();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('academy_lesson_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('academy_lessons')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });

        // Abonnement Academy : accès à tous les cours « inclus dans les abonnements » pendant la durée du plan.
        Schema::create('academy_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->unsignedSmallInteger('duration_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('academy_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('academy_courses')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('academy_plans')->nullOnDelete();
            $table->string('label', 190);
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('TND');
            $table->string('status', 16)->default('pending');
            // Prestataire de paiement (clé du registre) et référence chez lui : le paiement en ligne se branche ici.
            $table->string('provider', 32)->default('manual');
            $table->string('provider_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('academy_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('academy_plans')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('academy_orders')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['user_id', 'ends_at']);
        });

        // Calendrier Academy : sessions en direct (rattachées ou non à un cours).
        Schema::create('academy_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained('academy_courses')->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index('starts_at');
        });

        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_sessions');
        Schema::dropIfExists('academy_subscriptions');
        Schema::dropIfExists('academy_orders');
        Schema::dropIfExists('academy_plans');
        Schema::dropIfExists('academy_lesson_completions');
        Schema::dropIfExists('academy_enrollments');
        Schema::dropIfExists('academy_lessons');
        Schema::dropIfExists('academy_modules');
        Schema::dropIfExists('academy_courses');
    }
};
