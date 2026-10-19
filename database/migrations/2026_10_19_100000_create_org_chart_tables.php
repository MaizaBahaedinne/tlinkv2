<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Blocs de l'organigramme : postes, unites (branches) et clubs, relies en arbre.
        Schema::create('org_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('org_nodes')->restrictOnDelete();
            // hierarchy = lien hierarchique ; attachment = simple rattachement (conseillers, ancien president...)
            $table->string('relation_type', 20)->default('hierarchy');
            $table->string('kind', 20)->default('position'); // position | unit | club
            $table->string('category', 30); // presidency, direction, national_office, project, regional_club, university_club, highschool_club
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            // confirmed = relation confirmee ; to_validate = a valider par un administrateur
            $table->string('validation_status', 20)->default('confirmed');
            $table->string('validation_note', 500)->nullable();
            $table->string('children_layout', 20)->default('horizontal'); // horizontal | vertical
            $table->boolean('is_collapsed')->default(false);
            $table->integer('pos_dx')->default(0);
            $table->integer('pos_dy')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('system_key', 80)->nullable()->unique();
            $table->timestamps();
            $table->index(['parent_id', 'sort_order']);
            $table->index('category');
        });

        Schema::create('org_people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('job_title')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('contact_visible')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('full_name');
        });

        Schema::create('org_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('node_id')->constrained('org_nodes')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('org_people')->cascadeOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->unique(['node_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_assignments');
        Schema::dropIfExists('org_people');
        Schema::dropIfExists('org_nodes');
    }
};
