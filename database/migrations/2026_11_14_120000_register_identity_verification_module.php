<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Enregistre le module « Vérifications d'identité » dans le catalogue : il devient attribuable dans « Postes et droits ». */
    public function up(): void
    {
        ModuleCatalog::sync();
    }

    public function down(): void
    {
        // Le module reste au catalogue.
    }
};
