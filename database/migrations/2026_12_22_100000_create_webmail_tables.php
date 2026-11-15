<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webmail T.Link : une boîte partagée par entité (club, chapitre, projet ou Fondation), ses étiquettes locales
 * et son carnet d'adresses. Les messages restent sur le serveur de messagerie (OVH) ; T.Link n'en garde aucun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webmail_mailboxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->nullable()->constrained('org_nodes')->cascadeOnDelete();
            $table->string('scope', 60)->nullable();
            $table->string('address', 190)->unique();
            $table->string('display_name', 120)->nullable();
            $table->text('password');
            $table->text('signature')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('provisioned')->default(false);
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->unsignedInteger('unseen')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('scope');
        });

        Schema::create('webmail_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_id')->constrained('webmail_mailboxes')->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('email', 190);
            $table->timestamps();
            $table->unique(['mailbox_id', 'email']);
        });

        Schema::create('webmail_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_id')->constrained('webmail_mailboxes')->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('color', 20)->default('blue');
            $table->timestamps();
            $table->unique(['mailbox_id', 'name']);
        });

        Schema::create('webmail_message_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('label_id')->constrained('webmail_labels')->cascadeOnDelete();
            $table->string('folder', 190);
            $table->unsignedBigInteger('uid');
            $table->unique(['label_id', 'folder', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webmail_message_labels');
        Schema::dropIfExists('webmail_labels');
        Schema::dropIfExists('webmail_contacts');
        Schema::dropIfExists('webmail_mailboxes');
    }
};
