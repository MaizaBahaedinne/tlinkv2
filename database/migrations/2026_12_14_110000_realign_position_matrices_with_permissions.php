<?php

use App\Models\OrgNode;
use App\Support\AccessLevels;
use App\Support\ModuleCatalog;
use App\Support\ModulePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Un poste peut garder une permission (ex. approval.review) alors que sa matrice CRUD ne coche plus aucun module qui la porte :
     * la page répond alors « Les habilitations de votre poste ne couvrent pas cette action ». On recoche les modules manquants
     * (uniquement ceux dont la permission est déjà détenue, la matrice n'ajoute aucun droit).
     */
    public function up(): void
    {
        ModuleCatalog::sync();

        OrgNode::query()
            ->where('kind', 'position')
            ->whereNotNull('module_permissions')
            ->where('access_level', '!=', AccessLevels::NONE)
            ->each(function (OrgNode $node): void {
                $matrix = (array) $node->module_permissions;
                $held = AccessLevels::forPosition((string) $node->access_level, $node->permissions);
                $missing = array_values(array_diff($held, ModulePermissions::derivePermissions($matrix)));

                if ($missing === []) {
                    return;
                }

                $additions = array_filter(
                    ModulePermissions::fromPermissions($missing),
                    fn (array $actions, string $key): bool => in_array(ModulePermissions::MODULE_PERMISSIONS[$key] ?? null, $missing, true),
                    ARRAY_FILTER_USE_BOTH,
                );

                if ($additions !== []) {
                    $node->update(['module_permissions' => array_merge($matrix, $additions)]);
                }
            });
    }

    public function down(): void
    {
        // Les droits accordés restent en place.
    }
};
