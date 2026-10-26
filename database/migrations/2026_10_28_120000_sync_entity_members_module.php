<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Ajoute les modules manquants du catalogue (sans toucher aux existants) : « Membres de mon entité ».
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        // Le catalogue reste en place.
    }
};
