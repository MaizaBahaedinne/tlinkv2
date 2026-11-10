<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table): void {
            $table->id();
            // Destinataire (une ligne par adresse) : le contenu du message n'est volontairement pas conservé
            // (codes de confirmation et liens personnels).
            $table->string('to_email')->index();
            $table->string('to_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject', 255)->nullable();
            $table->string('template', 60)->nullable()->index();
            $table->string('status', 12)->default('pending')->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['to_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
