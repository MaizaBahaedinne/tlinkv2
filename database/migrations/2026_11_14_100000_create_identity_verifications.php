<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            // Documents chiffrés sur le disque privé ; effacés dès que la décision est prise.
            $table->string('id_front_path')->nullable();
            $table->string('id_back_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->timestamp('identity_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn('identity_verified_at');
        });

        Schema::dropIfExists('identity_verifications');
    }
};
