<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->text('motivation_letter')->nullable()->after('membership_type');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropColumn('motivation_letter');
        });
    }
};
