<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Validateurs désignés par entité (périmètre) : ils reçoivent les demandes d'adhésion de leur entité
     * et peuvent les accepter ou les refuser, au même titre que le validateur assigné.
     */
    public function up(): void
    {
        Schema::create('scope_validators', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 60);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['scope', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scope_validators');
    }
};
