<?php

use App\Models\OrgNode;
use App\Support\ModuleCatalog;
use App\Support\ModulePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Modules « Cartes et pointage » : les postes qui ont une matrice et gèrent déjà les événements (pointage, locaux)
     * ou les adhésions (cartes) les reçoivent, sinon les nouvelles pages répondent 403.
     */
    public function up(): void
    {
        ModuleCatalog::sync();

        $grants = [
            'cards.checkpoint' => 'event.manage',
            'cards.premises' => 'event.manage',
            'cards.cards' => 'membership.manage',
        ];

        OrgNode::query()
            ->where('kind', 'position')
            ->whereNotNull('module_permissions')
            ->each(function (OrgNode $node) use ($grants): void {
                $matrix = (array) $node->module_permissions;
                $derived = ModulePermissions::derivePermissions($matrix);
                $changed = false;

                foreach ($grants as $module => $permission) {
                    if (! isset($matrix[$module]) && in_array($permission, $derived, true)) {
                        $matrix[$module] = $module === 'cards.cards' ? ['read', 'update'] : ['read', 'create', 'update', 'delete'];
                        $changed = true;
                    }
                }

                if ($changed) {
                    $node->update(['module_permissions' => $matrix]);
                }
            });
    }

    public function down(): void
    {
        // Les droits accordés restent en place.
    }
};
