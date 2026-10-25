<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->boolean('is_site_news')->default(false)->after('comments_enabled');
            $table->index(['is_site_news', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['is_site_news', 'published_at']);
            $table->dropColumn('is_site_news');
        });
    }
};
