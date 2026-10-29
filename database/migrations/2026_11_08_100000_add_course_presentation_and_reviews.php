<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_courses', function (Blueprint $table): void {
            $table->string('instructor_title', 160)->nullable()->after('instructor_name');
            $table->text('instructor_bio')->nullable()->after('instructor_title');
            $table->string('instructor_photo_path')->nullable()->after('instructor_bio');
            // Une ligne par objectif / prérequis / public visé.
            $table->text('objectives')->nullable()->after('description');
            $table->text('prerequisites')->nullable()->after('objectives');
            $table->text('audience')->nullable()->after('prerequisites');
            $table->string('language', 40)->default('Français')->after('audience');
        });

        // Un avis par membre et par cours : note + commentaire sur la formation, note + commentaire sur le formateur.
        Schema::create('academy_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('course_rating');
            $table->text('course_comment')->nullable();
            $table->unsignedTinyInteger('instructor_rating')->nullable();
            $table->text('instructor_comment')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_reviews');

        Schema::table('academy_courses', function (Blueprint $table): void {
            $table->dropColumn(['instructor_title', 'instructor_bio', 'instructor_photo_path', 'objectives', 'prerequisites', 'audience', 'language']);
        });
    }
};
