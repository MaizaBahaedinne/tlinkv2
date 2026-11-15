<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Signature manuscrite (dessinée au doigt ou à la souris), enregistrée en image PNG avec la charte signée. */
    public function up(): void
    {
        if (! Schema::hasColumn('marketing_access_requests', 'charter_signature')) {
            Schema::table('marketing_access_requests', function (Blueprint $table): void {
                $table->longText('charter_signature')->nullable()->after('charter_signed_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketing_access_requests', 'charter_signature')) {
            Schema::table('marketing_access_requests', function (Blueprint $table): void {
                $table->dropColumn('charter_signature');
            });
        }
    }
};
