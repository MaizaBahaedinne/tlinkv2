<?php

use App\Models\OrgNode;
use App\Support\ModuleCatalog;
use App\Support\ModulePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Le module « Messagerie » est nouveau : les postes de portée globale qui ont une matrice et gèrent déjà
     * l'organisation (permission entity.manage) le reçoivent, sinon la page répond 403 (matrice sans la nouvelle clé).
     */
    public function up(): void
    {
        ModuleCatalog::sync();

        OrgNode::query()
            ->where('kind', 'position')
            ->whereNull('scope')
            ->whereNotNull('module_permissions')
            ->each(function (OrgNode $node): void {
                $matrix = (array) $node->module_permissions;

                if (isset($matrix['website.mail']) || ! in_array('entity.manage', ModulePermissions::derivePermissions($matrix), true)) {
                    return;
                }

                $matrix['website.mail'] = ['read', 'update'];
                $node->update(['module_permissions' => $matrix]);
            });
    }

    public function down(): void
    {
        // Les droits accordés restent en place.
    }
};
