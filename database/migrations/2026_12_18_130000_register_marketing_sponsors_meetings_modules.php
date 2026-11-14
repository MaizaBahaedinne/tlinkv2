<?php

use App\Models\OrgNode;
use App\Support\ModuleCatalog;
use App\Support\ModulePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Modules Marketing, Sponsors et partenaires, Réunions : les postes qui ont une matrice et gèrent déjà
     * les événements les reçoivent, sinon les nouvelles pages répondent 403. Le coffre marketing est accordé
     * sans droit de suppression : retirer un accès reste une décision à confier explicitement.
     */
    public function up(): void
    {
        ModuleCatalog::sync();

        $grants = [
            'marketing.accounts' => ['read', 'create', 'update'],
            'sponsoring.contacts' => ['read', 'create', 'update', 'delete'],
            'sponsoring.leads' => ['read', 'create', 'update', 'delete'],
            'meetings.meetings' => ['read', 'create', 'update', 'delete'],
        ];

        OrgNode::query()
            ->where('kind', 'position')
            ->whereNotNull('module_permissions')
            ->each(function (OrgNode $node) use ($grants): void {
                $matrix = (array) $node->module_permissions;
                $derived = ModulePermissions::derivePermissions($matrix);

                if (! in_array('event.manage', $derived, true)) {
                    return;
                }

                $changed = false;

                foreach ($grants as $module => $actions) {
                    if (! isset($matrix[$module])) {
                        $matrix[$module] = $actions;
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
