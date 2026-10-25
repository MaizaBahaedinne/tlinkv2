<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandates', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 25)->unique();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
            $table->string('bureau_name', 190);
            $table->boolean('is_current')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['start_year', 'end_year']);
            $table->index(['is_current', 'start_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandates');
    }
};
