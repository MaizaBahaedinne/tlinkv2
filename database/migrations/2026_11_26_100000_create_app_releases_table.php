<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_releases')) {
            // Journal des changements : une ligne par version, notes classées par catégorie (JSON).
            Schema::create('app_releases', function (Blueprint $table): void {
                $table->id();
                $table->string('version', 40)->unique();
                $table->string('title', 160);
                $table->text('summary')->nullable();
                $table->json('notes')->nullable();
                $table->string('status', 20)->default('draft'); // draft | published
                $table->timestamp('published_at')->nullable();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'published_at']);
            });
        }

        // Modules « Versions et changements » et « Déploiement » : attribuables dans « Postes et droits ».
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
