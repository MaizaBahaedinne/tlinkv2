<?php

use App\Models\OrgNode;
use App\Support\OrgChart\OrgChartInstaller;
use Illuminate\Database\Migrations\Migration;

/**
 * Le sommet de l'organigramme est la fondation (« Tunivisions Foundation »), pas la presidence :
 * on ajoute ce bloc au-dessus de l'ancien sommet. Sans effet si l'organigramme n'est pas installe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (OrgNode::query()->exists()) {
            app(OrgChartInstaller::class)->ensureRoot();
        }
    }

    public function down(): void
    {
        // Pas de retour arriere : le sommet est un bloc de structure.
    }
};
