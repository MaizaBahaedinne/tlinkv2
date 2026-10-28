<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Prix de participation en dinars (TND) : NULL = non précisé, 0 = gratuit.
            $table->decimal('price', 8, 2)->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('price');
        });
    }
};
