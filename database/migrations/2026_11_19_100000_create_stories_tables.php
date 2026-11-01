<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Story éphémère (photo ou vidéo) visible 24 h par les membres, en haut du fil.
        Schema::create('stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('path');
            $table->string('mime_type', 80);
            $table->string('caption', 160)->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });

        // Qui a vu quoi : sert à marquer les stories déjà vues et à compter les vues pour l'auteur.
        Schema::create('story_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['story_id', 'user_id']);
        });

        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
    }
};
