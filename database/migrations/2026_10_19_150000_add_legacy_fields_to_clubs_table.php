<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('slug');
            $table->string('email')->nullable()->after('governorate_id');
            $table->string('facebook_url', 500)->nullable()->after('email');
            $table->date('founded_on')->nullable()->after('facebook_url');
            $table->boolean('charter_signed')->default(false)->after('founded_on');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn(['legacy_id', 'email', 'facebook_url', 'founded_on', 'charter_signed']);
        });
    }
};
