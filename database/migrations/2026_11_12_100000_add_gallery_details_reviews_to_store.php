<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_products', function (Blueprint $table): void {
            $table->string('material', 120)->nullable()->after('description');
            $table->string('composition', 200)->nullable()->after('material');
            $table->string('weight', 60)->nullable()->after('composition');
            $table->string('fit', 80)->nullable()->after('weight');
            $table->text('care')->nullable()->after('fit');
            $table->text('details')->nullable()->after('care');
            $table->text('size_guide')->nullable()->after('details');
        });

        Schema::create('store_product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('store_colors')->nullOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        Schema::create('store_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('published');
            $table->timestamps();

            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_reviews');
        Schema::dropIfExists('store_product_images');

        Schema::table('store_products', function (Blueprint $table): void {
            $table->dropColumn(['material', 'composition', 'weight', 'fit', 'care', 'details', 'size_guide']);
        });
    }
};
