<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('site_news', 'feed_post_id')) {
            return;
        }

        // Publication du fil T.Link qui relaie l'article (optionnelle) : ses réactions et commentaires s'affichent sous l'article.
        Schema::table('site_news', function (Blueprint $table): void {
            $table->foreignId('feed_post_id')->nullable()->after('author_id')->constrained('posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_news', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('feed_post_id');
        });
    }
};
