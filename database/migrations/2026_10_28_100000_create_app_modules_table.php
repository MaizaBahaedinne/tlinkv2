<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('app_modules')->restrictOnDelete();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('actions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('app_modules');
    }
};
