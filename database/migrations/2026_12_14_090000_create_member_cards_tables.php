<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une carte par adhésion validée et par mandat : elle se renouvelle avec le mandat tant que l'adhésion est active, sinon elle expire.
        Schema::create('member_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mandate_id')->constrained()->cascadeOnDelete();
            $table->string('number', 16)->unique();
            // Clé de signature des QR codes de cette carte (chiffrée) : elle ne quitte jamais le serveur.
            $table->text('secret');
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['membership_id', 'mandate_id']);
            $table->index(['user_id', 'status']);
        });

        // Locaux (siège, salle, espace de coworking…) où l'on peut pointer.
        Schema::create('premises', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('address', 255)->nullable();
            $table->string('scope', 40)->nullable()->index();
            $table->foreignId('node_id')->nullable()->constrained('org_nodes')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pointages : une ligne par entrée ou sortie, rattachée à un module (activité, événement, local…).
        Schema::create('card_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label', 200);
            // Périmètre et club de la cible au moment du pointage : ils servent à limiter l'historique.
            $table->string('scope', 40)->nullable()->index();
            $table->unsignedBigInteger('node_id')->nullable()->index();
            $table->string('direction', 8);
            $table->string('method', 12)->default('qr');
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at')->index();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'scanned_at']);
            $table->index(['member_card_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_scans');
        Schema::dropIfExists('premises');
        Schema::dropIfExists('member_cards');
    }
};
