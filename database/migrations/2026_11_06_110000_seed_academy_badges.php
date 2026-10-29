<?php

use App\Support\Gamification\StarterBadges;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Les tests partent d'une base vide de badges et créent leurs propres missions.
        if (! app()->runningUnitTests()) {
            StarterBadges::installAcademy();
        }
    }

    public function down(): void
    {
        // Les badges restent en place.
    }
};
