<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table): void {
                $table->id();
                $table->string('email', 190)->unique();
                $table->string('locale', 5)->default('fr');
                $table->string('unsubscribe_token', 64)->unique();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('subscribed_at')->nullable();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();
            });
        }

        // Ajoute le module « Newsletter » au catalogue (sans toucher aux modules existants).
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
