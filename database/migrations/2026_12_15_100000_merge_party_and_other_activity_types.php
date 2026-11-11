<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Types d'activité officiels : conférence, formation, événement général, team building, camping, compétition.
        // Les anciens types « soirée » et « autre » deviennent « événement général ».
        if (Schema::hasTable('club_activities')) {
            DB::table('club_activities')->whereIn('type', ['party', 'other'])->update(['type' => 'event']);
        }
    }

    public function down(): void
    {
        // Les anciens types ne peuvent pas être restaurés : ils ont été fusionnés dans « événement général ».
    }
};
