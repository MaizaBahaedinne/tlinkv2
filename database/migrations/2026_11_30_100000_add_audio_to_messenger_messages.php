<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages vocaux de la messagerie (conversations directes et groupes) : fichier audio privé et durée en secondes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messenger_messages') || Schema::hasColumn('messenger_messages', 'audio_path')) {
            return;
        }

        Schema::table('messenger_messages', function (Blueprint $table): void {
            $table->string('audio_path')->nullable()->after('attachment_name');
            $table->unsignedSmallInteger('audio_seconds')->nullable()->after('audio_path');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('messenger_messages', 'audio_path')) {
            Schema::table('messenger_messages', function (Blueprint $table): void {
                $table->dropColumn(['audio_path', 'audio_seconds']);
            });
        }
    }
};
