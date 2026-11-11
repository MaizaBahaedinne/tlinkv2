<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pastille « Mes tâches » : une affectation reste « non vue » tant que la personne n'a pas ouvert sa liste.
        Schema::table('activity_task_assignees', function (Blueprint $table): void {
            $table->timestamp('seen_at')->nullable()->after('user_id');
        });

        // Les affectations déjà en place ne déclenchent pas de pastille.
        DB::table('activity_task_assignees')->update(['seen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('activity_task_assignees', function (Blueprint $table): void {
            $table->dropColumn('seen_at');
        });
    }
};
