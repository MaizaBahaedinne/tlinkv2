<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversation directe (2 membres, une seule par paire grâce à `direct_key`) ou groupe.
        Schema::create('messenger_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 10)->default('direct');
            $table->string('title', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direct_key', 40)->nullable()->unique();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->dateTime('last_message_at')->nullable()->index();
            $table->timestamps();
        });

        // `last_read_message_id` : tous les messages d'identifiant inférieur ou égal sont lus par ce participant.
        Schema::create('messenger_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('messenger_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member');
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->dateTime('left_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        // Un message supprimé reste en base (deleted_at) : la conversation garde sa chronologie.
        Schema::create('messenger_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('messenger_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('messenger_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_id']);
        });

        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_blocks');
        Schema::dropIfExists('messenger_messages');
        Schema::dropIfExists('messenger_participants');
        Schema::dropIfExists('messenger_conversations');
    }
};
