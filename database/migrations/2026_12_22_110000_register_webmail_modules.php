<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Enregistre les modules du webmail. Aucun poste n'est créé et aucun droit n'est accordé : on les attribue
     * poste par poste dans la matrice (Gouvernance > Modules).
     */
    public function up(): void
    {
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        // Les modules et les droits accordés restent en place.
    }
};
