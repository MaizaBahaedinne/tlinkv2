<?php

use App\Models\Governorate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coordonnées des gouvernorats pour la carte de la page publique « Nos clubs ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governorates', function (Blueprint $table): void {
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
        });

        foreach (Governorate::COORDINATES as $slug => [$latitude, $longitude]) {
            DB::table('governorates')->where('slug', $slug)->update(['latitude' => $latitude, 'longitude' => $longitude]);
        }
    }

    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
