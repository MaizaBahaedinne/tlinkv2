<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandate_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mandate_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 60);
            $table->foreignId('node_id')->nullable()->constrained('org_nodes')->nullOnDelete();
            $table->string('position_title', 190)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'mandate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandate_participations');
    }
};
