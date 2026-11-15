<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes autorisés choisis à la main sur la fiche d'une boîte (en plus de ceux dont le poste ouvre le webmail), avec leurs
 * droits ; la signature d'une boîte devient un long texte (HTML accepté).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webmail_mailbox_users')) {
            Schema::create('webmail_mailbox_users', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('mailbox_id')->constrained('webmail_mailboxes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->json('actions');
                $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['mailbox_id', 'user_id']);
            });
        }

        Schema::table('webmail_mailboxes', function (Blueprint $table): void {
            $table->longText('signature')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webmail_mailbox_users');
    }
};
