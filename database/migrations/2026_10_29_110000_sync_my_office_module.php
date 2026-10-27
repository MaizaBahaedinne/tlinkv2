<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Ajoute le module « Mon bureau » au catalogue (sans toucher aux modules existants).
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        // Le catalogue reste en place.
    }
};
