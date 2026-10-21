<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Photographie des permissions actives d'un utilisateur pour la requete en cours.
 * Une seule requete de chargement, puis toutes les verifications se font en memoire.
 */
class AdminContext
{
    public const ATTRIBUTE = 'admin.context';

    /** @var array<string, array<int, string|null>> */
    private array $permissionScopes = [];

    public function __construct(public readonly User $user)
    {
        // Les droits viennent des postes occupés dans l'organigramme (niveau d'accès du poste).
        $this->permissionScopes = OrgAccess::grants($user);
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

        return array_values(array_unique($this->permissionScopes[$permission]));
    }

    public function isGlobal(string $permission): bool
    {
        return $this->scope($permission) === [];
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
