<?php

namespace App\Support;

use App\Models\OrgNode;
use App\Models\User;
use Closure;

/**
 * Simulateur de rôles : un administrateur global « change de poste » virtuellement pour tester l'interface et les
 * autorisations d'un poste, sans quitter son compte ni modifier l'organigramme.
 *
 * Pendant la simulation, les droits de l'utilisateur sont ceux du seul poste choisi (voir OrgAccess::positions).
 * L'état vit dans le conteneur de la requête (posé par ApplyRoleSimulation à partir de la session) : il ne peut donc pas
 * fuiter d'une requête à l'autre. Les actions réalisées restent des actions réelles de l'administrateur.
 */
final class RoleSimulator
{
    public const SESSION_KEY = 'role_simulation';

    private const BINDING = 'role.simulation';

    /** Modules de gestion des postes et des droits : un poste qui les ouvre n'est jamais simulable. */
    private const PROTECTED_MODULES = ['organization.positions', 'governance.modules'];

    /** Poste simulé pour cet utilisateur dans la requête en cours (null : aucune simulation). */
    public static function nodeFor(User $user): ?OrgNode
    {
        $state = self::state();

        return $state !== null && $state['user_id'] === $user->id ? $state['node'] : null;
    }

    public static function active(): bool
    {
        return self::state() !== null;
    }

    public static function activate(User $user, OrgNode $node): void
    {
        app()->instance(self::BINDING, ['user_id' => (int) $user->id, 'node' => $node]);
    }

    public static function deactivate(): void
    {
        app()->forgetInstance(self::BINDING);
    }

    /**
     * Exécute un contrôle avec les vrais droits de l'utilisateur, simulation suspendue.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public static function withoutSimulation(Closure $callback): mixed
    {
        $state = self::state();
        self::deactivate();

        try {
            return $callback();
        } finally {
            if ($state !== null) {
                app()->instance(self::BINDING, $state);
            }
        }
    }

    /** Seul un administrateur global (droits réels) peut utiliser le simulateur. */
    public static function canUse(User $user): bool
    {
        return self::withoutSimulation(fn (): bool => (new AdminContext($user))->isGlobal('entity.manage'));
    }

    /**
     * Un poste est simulable s'il porte des droits d'administration, sans être celui d'un administrateur global
     * ni d'un gestionnaire des postes et des droits (organization.positions, governance.modules).
     */
    public static function isSimulable(OrgNode $node): bool
    {
        return self::eligible($node) && OrgAccess::nodeGrants($node) !== [];
    }

    /** Règles d'exclusion seules (sans interroger le périmètre du poste) : utile pour filtrer de longues listes. */
    public static function eligible(OrgNode $node): bool
    {
        if ($node->kind !== 'position' || $node->access_level === AccessLevels::NONE) {
            return false;
        }

        $level = (string) $node->access_level;

        if (AccessLevels::isGlobal($level)) {
            return false;
        }

        $permissions = AccessLevels::forPosition($level, $node->permissions);

        if ($permissions === []) {
            return false;
        }

        if (in_array('entity.manage', $permissions, true)) {
            $matrix = (array) $node->module_permissions;

            // Sans matrice, le niveau d'accès ouvre tous les modules, dont celui des postes et des droits.
            if ($matrix === []) {
                return false;
            }

            foreach (self::PROTECTED_MODULES as $module) {
                if (! empty($matrix[$module])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array{user_id: int, node: OrgNode}|null
     */
    private static function state(): ?array
    {
        return app()->bound(self::BINDING) ? app(self::BINDING) : null;
    }
}
