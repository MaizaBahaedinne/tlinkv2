<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une tâche peut être confiée à plusieurs membres.
        Schema::create('activity_task_assignees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('activity_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        if (Schema::hasColumn('activity_tasks', 'assignee_id')) {
            $now = now();

            foreach (DB::table('activity_tasks')->whereNotNull('assignee_id')->get(['id', 'assignee_id']) as $task) {
                DB::table('activity_task_assignees')->insert(['task_id' => $task->id, 'user_id' => $task->assignee_id, 'created_at' => $now, 'updated_at' => $now]);
            }

            Schema::table('activity_tasks', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('assignee_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_task_assignees');
    }
};
