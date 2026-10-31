<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table): void {
            // Saisis par le membre, chiffrés, comparés par l'administrateur à la carte photographiée, effacés à la décision.
            $table->text('cin_number')->nullable()->after('selfie_path');
            $table->text('card_issued_on')->nullable()->after('cin_number');
            $table->text('card_issued_place')->nullable()->after('card_issued_on');
        });
    }

    public function down(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table): void {
            $table->dropColumn(['cin_number', 'card_issued_on', 'card_issued_place']);
        });
    }
};
