<?php

namespace App\Support;

use App\Models\AppModule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Photographie des permissions actives d'un utilisateur pour la requete en cours.
 * Une seule requete de chargement, puis toutes les verifications se font en memoire.
 */
class AdminContext
{
    public const ATTRIBUTE = 'admin.context';

    /** Modules jamais désactivables : on ne doit pas pouvoir se verrouiller hors de la gestion des droits. */
    private const ALWAYS_ENABLED = ['governance.modules', 'organization.positions'];

    /** @var array<string, array<int, string|null>> */
    private array $permissionScopes = [];

    /** @var array<int, array{permissions: array<int, string>, scope: string|null, matrix: array<string, array<int, string>>|null}> */
    private array $positions = [];

    /** @var array<string, true>|null clés des modules désactivés dans le catalogue (ou dont un parent l'est) */
    private ?array $disabledModules = null;

    public function __construct(public readonly User $user)
    {
        // Les droits viennent des postes occupés dans l'organigramme (niveau d'accès du poste).
        $this->positions = OrgAccess::positions($user);
        $this->permissionScopes = OrgAccess::aggregate($this->positions);
    }

    public static function of(Request $request): self
    {
        $context = $request->attributes->get(self::ATTRIBUTE);

        if (! $context instanceof self) {
            $context = new self($request->user());
            $request->attributes->set(self::ATTRIBUTE, $context);
        }

        return $context;
    }

    public function can(string $permission): bool
    {
        return isset($this->permissionScopes[$permission]);
    }

    /**
     * @param array<int, string> $permissions
     */
    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * null  => permission absente
     * []    => perimetre global (tous les perimetres)
     * [slugs] => limite aux perimetres listes
     *
     * @return array<int, string>|null
     */
    public function scope(string $permission): ?array
    {
        if (! $this->can($permission)) {
            return null;
        }

        if (in_array(null, $this->permissionScopes[$permission], true)) {
            return [];
        }

        return OrgScopes::expandManaged(array_values(array_unique($this->permissionScopes[$permission])));
    }

    /**
     * Accès à un module de l'administration : le niveau d'accès d'un poste donne la permission,
     * et la matrice CRUD du poste (si elle existe) la restreint aux modules cochés.
     * La matrice n'ajoute jamais de droit : sans la permission, l'accès est refusé.
     *
     * @param array<int, string> $permissions au moins une
     * @param array<int, string> $actions au moins une
     */
    public function allowsModule(array $permissions, string $moduleKey, array $actions = ['read']): bool
    {
        // Le catalogue pilote le menu : un module désactivé disparaît pour tout le monde.
        if (! $this->moduleEnabled($moduleKey)) {
            return false;
        }

        foreach ($this->positions as $position) {
            if (array_intersect($permissions, $position['permissions']) === []) {
                continue;
            }

            if ($position['matrix'] === null || array_intersect($actions, $position['matrix'][$moduleKey] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    private function moduleEnabled(string $moduleKey): bool
    {
        if (in_array($moduleKey, self::ALWAYS_ENABLED, true)) {
            return true;
        }

        if ($this->disabledModules === null) {
            $this->disabledModules = [];

            if (Schema::hasTable('app_modules')) {
                $modules = AppModule::query()->get(['id', 'parent_id', 'key', 'is_active']);
                $byId = $modules->keyBy('id');

                foreach ($modules as $module) {
                    for ($current = $module, $guard = 0; $current !== null && $guard < 5; $guard++) {
                        if (! $current->is_active) {
                            $this->disabledModules[$module->key] = true;
                            break;
                        }

                        $current = $current->parent_id !== null ? ($byId[$current->parent_id] ?? null) : null;
                    }
                }
            }
        }

        return ! isset($this->disabledModules[$moduleKey]);
    }

    public function isGlobal(string $permission): bool
    {
        return $this->scope($permission) === [];
    }

    /**
     * Entités propres : les périmètres des postes occupés, sans leurs sous-périmètres.
     *
     * null => permission absente ; [] => périmètre global ; [slugs] => entités propres.
     *
     * @return array<int, string>|null
     */
    public function ownScopes(string $permission): ?array
    {
        if (! $this->can($permission)) {
            return null;
        }

        if (in_array(null, $this->permissionScopes[$permission], true)) {
            return [];
        }

        return array_values(array_unique(array_filter($this->permissionScopes[$permission], 'is_string')));
    }

    public function canManageScope(string $permission, ?string $scopeSlug): bool
    {
        $scope = $this->scope($permission);

        if ($scope === null) {
            return false;
        }

        if ($scope === []) {
            return true;
        }

        return $scopeSlug !== null && in_array($scopeSlug, $scope, true);
    }
}
