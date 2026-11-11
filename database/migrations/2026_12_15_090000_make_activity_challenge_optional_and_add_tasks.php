<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un club n'est plus obligé de travailler sous un défi : l'activité libre n'a ni thématique ni barème.
        Schema::table('club_activities', function (Blueprint $table): void {
            $table->unsignedBigInteger('challenge_id')->nullable()->change();
        });

        // Tâches d'une activité : reprises de la liste type de son type d'activité, adaptées, affectées et suivies.
        Schema::create('activity_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
            $table->string('team', 20);
            $table->string('label', 255);
            $table->text('notes')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_at')->nullable();
            $table->string('status', 12)->default('todo');
            $table->boolean('from_template')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['activity_id', 'team']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_tasks');
    }
};
