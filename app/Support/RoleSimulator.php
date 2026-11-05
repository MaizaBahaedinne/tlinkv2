<?php

namespace App\Support;

use App\Models\OrgNode;
use App\Models\Setting;
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

    /** Réglage en base : comptes autorisés à utiliser le simulateur (modifiable depuis l'administration). */
    public const SETTING_GROUP = 'admin';

    public const SETTING_KEY = 'role_simulator';

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

    /**
     * Peut utiliser le simulateur : un administrateur global (droits réels) ou un compte autorisé par l'un d'eux
     * (liste modifiable dans Admin > Simulateur de rôles). Résultat mémorisé pour la requête en cours.
     */
    public static function canUse(User $user): bool
    {
        return self::remember('use', $user, fn (): bool => self::isGlobalAdmin($user)
            || ($user->is_active !== false && in_array($user->id, self::allowedUserIds(), true)));
    }

    /** Simulateur proposable maintenant : utilisable, et aucune simulation n'est déjà en cours. */
    public static function available(User $user): bool
    {
        return ! self::active() && self::canUse($user);
    }

    /** Administrateur global au sens des droits réels du compte, simulation suspendue. */
    public static function isGlobalAdmin(User $user): bool
    {
        return self::remember('global', $user, fn (): bool => self::withoutSimulation(
            fn (): bool => in_array(null, OrgAccess::grants($user)['entity.manage'] ?? [], true)
        ));
    }

    /** Identifiants des comptes autorisés à utiliser le simulateur en plus des administrateurs globaux. */
    public static function allowedUserIds(): array
    {
        try {
            $value = Setting::query()->where('group', self::SETTING_GROUP)->where('key', self::SETTING_KEY)->value('value');
        } catch (\Throwable) {
            return [];
        }

        $data = $value ? json_decode((string) $value, true) : null;

        return array_values(array_unique(array_map('intval', (array) (is_array($data) ? ($data['user_ids'] ?? []) : []))));
    }

    /** @param array<int, int> $ids */
    public static function saveAllowedUserIds(array $ids): void
    {
        Setting::query()->updateOrCreate(
            ['group' => self::SETTING_GROUP, 'key' => self::SETTING_KEY],
            ['value' => json_encode(['user_ids' => array_values(array_unique(array_map('intval', $ids)))])],
        );
    }

    /**
     * @param Closure(): bool $resolve
     */
    private static function remember(string $name, User $user, Closure $resolve): bool
    {
        $request = request();
        $key = 'role.simulator.'.$name.'.'.$user->id;

        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, $resolve());
        }

        return (bool) $request->attributes->get($key);
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
