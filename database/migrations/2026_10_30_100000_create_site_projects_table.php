<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_projects')) {
            Schema::create('site_projects', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 80)->unique();
                $table->json('name');          // {fr, en, ar}
                $table->json('summary')->nullable();
                $table->json('body')->nullable();
                $table->json('stats')->nullable(); // [{value, label: {fr, en, ar}}]
                $table->string('cover_path')->nullable();
                $table->string('status', 20)->default('ongoing');
                $table->unsignedSmallInteger('started_year')->nullable();
                $table->string('website_url')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_published')->default(false);
                $table->timestamps();
            });
        }

        // Ajoute le module « Projets » au catalogue (sans toucher aux modules existants).
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_projects');
    }
};
