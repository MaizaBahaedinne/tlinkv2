<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->index(['post_id', 'viewed_at']);
            $table->index(['post_id', 'user_id']);
            $table->index(['post_id', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_views');
    }
};
