<?php

namespace App\Support;

use App\Models\AppModule;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;

/**
 * Habilitations CRUD par module : la matrice se règle sur chaque poste (org_nodes.module_permissions),
 * et une personne cumule les matrices des postes qu'elle occupe, chacune limitée au périmètre de son poste.
 */
final class ModulePermissions
{
    /**
     * Nettoie une matrice saisie : modules actifs connus, actions supportées, « consulter » toujours incluse
     * dès qu'une autre action est accordée (on ne modifie pas ce qu'on ne peut pas voir).
     *
     * @param array<string, mixed> $input clé de module => liste d'actions
     * @return array<string, array<int, string>>
     */
    public static function normalize(array $input): array
    {
        $result = [];

        foreach (AppModule::query()->where('is_active', true)->get(['key', 'actions']) as $module) {
            $supported = $module->supportedActions();
            $asked = array_values(array_intersect($supported, array_map('strval', (array) ($input[$module->key] ?? []))));

            if ($asked === []) {
                continue;
            }

            if (in_array('read', $supported, true) && ! in_array('read', $asked, true)) {
                $asked[] = 'read';
            }

            $result[$module->key] = array_values(array_intersect(array_keys(AppModule::ACTIONS), $asked));
        }

        return $result;
    }

    /**
     * Droits d'un utilisateur : clé de module => action => périmètres (null = toute la plateforme).
     * Un poste sans périmètre (explicite ou hérité) n'accorde rien, sauf s'il agit sur toute la plateforme.
     *
     * @return array<string, array<string, array<int, string|null>>>
     */
    public static function grants(User $user): array
    {
        $today = now()->toDateString();

        $assignments = OrgAssignment::query()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('node', fn ($query) => $query->where('kind', 'position')->whereNotNull('module_permissions'))
            ->with('node')
            ->get();

        $active = AppModule::query()->where('is_active', true)->pluck('key')->flip();
        $grants = [];

        foreach ($assignments as $assignment) {
            /** @var OrgNode $node */
            $node = $assignment->node;
            $matrix = (array) $node->module_permissions;

            if ($matrix === []) {
                continue;
            }

            $everywhere = AccessLevels::reachesEverywhere((string) $node->access_level, $node->permissions);
            $scope = $everywhere ? null : OrgAccess::scopeOf($node);

            if (! $everywhere && $scope === null) {
                continue;
            }

            foreach ($matrix as $key => $actions) {
                if (! isset($active[$key])) {
                    continue;
                }

                foreach ((array) $actions as $action) {
                    $grants[$key][$action][] = $scope;
                }
            }
        }

        return $grants;
    }

    /**
     * L'utilisateur peut-il faire cette action sur ce module (dans le périmètre donné, ou n'importe lequel si null) ?
     */
    public static function can(User $user, string $moduleKey, string $action, ?string $scope = null): bool
    {
        $scopes = self::grants($user)[$moduleKey][$action] ?? [];

        if ($scopes === []) {
            return false;
        }

        if ($scope === null || in_array(null, $scopes, true)) {
            return true;
        }

        foreach ($scopes as $granted) {
            if (OrgScopes::covers((string) $granted, $scope)) {
                return true;
            }
        }

        return false;
    }
}
