<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mandate_participations', function (Blueprint $table): void {
            // Les participations déjà enregistrées (simple membre) restent acceptées.
            $table->string('status', 20)->default('approved')->after('position_title');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('mandate_participations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'rejection_reason', 'approved_at']);
        });
    }
};
