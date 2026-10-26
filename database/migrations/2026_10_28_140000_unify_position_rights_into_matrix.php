<?php

use App\Models\OrgNode;
use App\Support\AccessLevels;
use App\Support\ModuleCatalog;
use App\Support\ModulePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Une seule source de droits : la matrice CRUD. Les postes qui n'avaient qu'un niveau d'accès ou des droits cochés
     * reçoivent la matrice équivalente (toutes les actions des modules concernés) ; leur comportement ne change pas.
     */
    public function up(): void
    {
        ModuleCatalog::sync();

        OrgNode::query()
            ->where('kind', 'position')
            ->whereNull('role_key')
            ->where('access_level', '!=', AccessLevels::NONE)
            ->whereNull('module_permissions')
            ->each(function (OrgNode $node): void {
                $matrix = ModulePermissions::fromPermissions(AccessLevels::forPosition((string) $node->access_level, $node->permissions));

                if ($matrix !== []) {
                    $node->update(['module_permissions' => $matrix]);
                }
            });
    }

    public function down(): void
    {
        // Les matrices restent en place : les niveaux d'accès d'origine n'ont pas été modifiés.
    }
};
