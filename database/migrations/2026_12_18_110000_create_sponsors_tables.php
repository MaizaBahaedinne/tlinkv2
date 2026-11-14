<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carnet d'adresses des sponsors et partenaires de la Fondation et de ses clubs : organisations, interlocuteurs
 * et pistes (démarches de sponsoring ou de partenariat menées par un club).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sponsors')) {
            Schema::create('sponsors', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 190);
                $table->string('relation', 20)->default('sponsor');
                $table->string('status', 20)->default('prospect');
                $table->string('sector', 120)->nullable();
                $table->string('website', 255)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('city', 120)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('owner_club_id')->nullable()->constrained('org_nodes')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'relation']);
            });
        }

        if (! Schema::hasTable('sponsor_contacts')) {
            Schema::create('sponsor_contacts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sponsor_id')->constrained('sponsors')->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('job_title', 160)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 40)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sponsor_leads')) {
            Schema::create('sponsor_leads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sponsor_id')->constrained('sponsors')->cascadeOnDelete();
                $table->foreignId('club_id')->nullable()->constrained('org_nodes')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('sponsor_contacts')->nullOnDelete();
                $table->string('title', 190);
                $table->string('kind', 20)->default('sponsoring');
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('status', 20)->default('idea');
                $table->text('next_action')->nullable();
                $table->date('next_action_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['club_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_leads');
        Schema::dropIfExists('sponsor_contacts');
        Schema::dropIfExists('sponsors');
    }
};
