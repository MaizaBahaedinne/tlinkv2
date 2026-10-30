<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mandates', function (Blueprint $table): void {
            $table->string('status', 20)->default('closed')->after('is_current');
            $table->index('status');
        });

        $currentStart = DB::table('mandates')->where('is_current', true)->value('start_year') ?? (int) now()->year;

        DB::table('mandates')->where('is_current', true)->update(['status' => 'current']);
        DB::table('mandates')->where('is_current', false)->where('start_year', '>', $currentStart)->update(['status' => 'upcoming']);
    }

    public function down(): void
    {
        Schema::table('mandates', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
