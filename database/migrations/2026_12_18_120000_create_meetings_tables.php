<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion des réunions : date, lieu, sujet, durée, ordre du jour, invitations, présences et décision par point.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meetings')) {
            Schema::create('meetings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('club_id')->nullable()->constrained('org_nodes')->nullOnDelete();
                $table->string('title', 190);
                $table->text('subject')->nullable();
                $table->dateTime('starts_at');
                $table->unsignedSmallInteger('duration_minutes')->default(60);
                $table->string('location', 190)->nullable();
                $table->string('online_url', 255)->nullable();
                $table->string('status', 20)->default('planned');
                $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('guests')->nullable();
                $table->text('minutes')->nullable();
                $table->timestamp('invitations_sent_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['club_id', 'starts_at']);
            });
        }

        if (! Schema::hasTable('meeting_agenda_items')) {
            Schema::create('meeting_agenda_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
                $table->unsignedSmallInteger('position')->default(0);
                $table->string('title', 190);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->foreignId('presenter_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('outcome', 20)->default('pending');
                $table->text('decision')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('meeting_attendees')) {
            Schema::create('meeting_attendees', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('response', 20)->default('pending');
                $table->string('attendance', 20)->nullable();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->unique(['meeting_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meeting_agenda_items');
        Schema::dropIfExists('meetings');
    }
};
