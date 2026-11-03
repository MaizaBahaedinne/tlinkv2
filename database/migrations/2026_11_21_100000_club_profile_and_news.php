<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Page profil d'un club : couverture, adresse, réseaux, et actualités publiées par le club. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->string('cover_path')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('linkedin_url', 500)->nullable();
        });

        Schema::create('club_news', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained('org_nodes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('cover_path')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['club_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_news');

        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropColumn(['cover_path', 'address', 'website_url', 'instagram_url', 'linkedin_url']);
        });
    }
};
