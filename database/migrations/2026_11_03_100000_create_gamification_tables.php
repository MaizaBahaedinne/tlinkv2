<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Badge (bronze, argent, or, platine) obtenu en réussissant toutes ses missions.
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('tier', 16)->default('bronze');
            $table->string('icon', 80)->default('fa-solid fa-medal');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Mission = un niveau à franchir pour obtenir le badge.
        Schema::create('missions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Point de contrôle = une activité mesurée (trigger_key) et le seuil à atteindre.
        Schema::create('mission_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('trigger_key', 60);
            $table->unsignedInteger('target')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('trigger_key');
        });

        Schema::create('user_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
        });

        // Ajoute le module « Badges et missions » au catalogue (sans toucher aux modules existants).
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('mission_checkpoints');
        Schema::dropIfExists('missions');
        Schema::dropIfExists('badges');
    }
};
