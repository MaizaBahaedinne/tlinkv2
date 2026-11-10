<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('node_id')->constrained('org_nodes')->cascadeOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('email');
            // Le jeton en clair n'existe que dans l'e-mail envoyé : seule son empreinte est conservée.
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('send_count')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['node_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_invitations');
    }
};
