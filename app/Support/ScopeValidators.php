<?php

namespace App\Support;

use App\Models\ScopeValidator;
use Illuminate\Support\Facades\Schema;

/**
 * Validateurs désignés par entité : la liste se règle dans Structure > Périmètres.
 * Sans validateur désigné, le choix automatique de ApprovalResolver s'applique.
 */
final class ScopeValidators
{
    private static ?bool $ready = null;

    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('scope_validators');
    }

    /**
     * @return array<int, int> identifiants des validateurs de l'entité, dans l'ordre de désignation
     */
    public static function ids(string $scope): array
    {
        if (! self::ready()) {
            return [];
        }

        return ScopeValidator::query()->where('scope', $scope)->orderBy('id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<int, string> entités dont l'utilisateur est validateur
     */
    public static function scopesOf(int $userId): array
    {
        if (! self::ready()) {
            return [];
        }

        return ScopeValidator::query()->where('user_id', $userId)->pluck('scope')->all();
    }

    /**
     * Comptes qui peuvent être désignés : ceux qui détiennent déjà le droit de valider pour cette entité.
     *
     * @return array<int, int>
     */
    public static function candidates(string $scope): array
    {
        return OrgAccess::userIdsWith('approval.review', $scope, 0);
    }
}
