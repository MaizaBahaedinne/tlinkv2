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

    /** @var array<string, array<int, int|null>> */
    private array $entityScopes = [];

    public function __construct(public readonly User $user)
    {
        // Les droits viennent des postes occupés dans l'organigramme (niveau d'accès du poste).
        $this->entityScopes = OrgAccess::grants($user);
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
        return isset($this->entityScopes[$permission]);
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
     * []    => perimetre global (toutes les entites)
     * [ids] => limite aux entites listees
     *
     * @return array<int, int>|null
     */
    public function scope(string $permission): ?array
    {
        if (! $this->can($permission)) {
            return null;
        }

        if (in_array(null, $this->entityScopes[$permission], true)) {
            return [];
        }

        return array_values(array_unique($this->entityScopes[$permission]));
    }

    public function isGlobal(string $permission): bool
    {
        return $this->scope($permission) === [];
    }

    public function canManageEntity(string $permission, ?int $entityId): bool
    {
        $scope = $this->scope($permission);

        if ($scope === null) {
            return false;
        }

        if ($scope === []) {
            return true;
        }

        return $entityId !== null && in_array($entityId, $scope, true);
    }
}
