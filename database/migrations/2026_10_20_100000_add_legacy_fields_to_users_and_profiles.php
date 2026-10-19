<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id');
            $table->boolean('is_active')->default(true)->after('password');
            $table->text('deactivated_reason')->nullable()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('deactivated_reason');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable()->after('headline');
            $table->string('phone_secondary', 20)->nullable()->after('phone');
            $table->string('gender', 10)->nullable()->after('phone_secondary');
            $table->date('birth_date')->nullable()->after('gender');
            $table->text('address')->nullable()->after('birth_date');
            $table->foreignId('governorate_id')->nullable()->after('address')->constrained()->nullOnDelete();
            $table->string('delegation')->nullable()->after('governorate_id');
            // Numero de CIN : donnee sensible, stockee chiffree (cast `encrypted`).
            $table->text('cin')->nullable()->after('delegation');
            $table->string('facebook_url', 500)->nullable()->after('cin');
            $table->string('instagram_url', 500)->nullable()->after('facebook_url');
            $table->string('linkedin_url', 500)->nullable()->after('instagram_url');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('governorate_id');
            $table->dropColumn([
                'phone', 'phone_secondary', 'gender', 'birth_date', 'address', 'delegation',
                'cin', 'facebook_url', 'instagram_url', 'linkedin_url',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn(['legacy_id', 'is_active', 'deactivated_reason', 'deactivated_at']);
        });
    }
};
