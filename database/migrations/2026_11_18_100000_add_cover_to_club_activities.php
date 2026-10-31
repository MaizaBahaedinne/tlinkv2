<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_activities', function (Blueprint $table): void {
            $table->string('cover_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('club_activities', function (Blueprint $table): void {
            $table->dropColumn('cover_path');
        });
    }
};
