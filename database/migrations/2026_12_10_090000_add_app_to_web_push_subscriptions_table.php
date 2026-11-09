<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque application (T.Link, Store, Academy, Messenger) a son propre sous-domaine, donc ses propres abonnements : un navigateur
 * abonné sur la messagerie ne reçoit pas les notifications de T.Link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_push_subscriptions', function (Blueprint $table): void {
            $table->string('app', 24)->default('app')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('web_push_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('app');
        });
    }
};
