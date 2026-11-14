<?php

namespace App\Support;

use App\Models\OrgNode;
use App\Models\User;
use App\Support\Challenges\ActivityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Portée « clubs » des modules marketing, sponsors et réunions : un responsable de club n'agit que sur son club,
 * un responsable de périmètre sur les clubs de ce périmètre, l'administrateur global sur tout (y compris la Fondation,
 * c'est-à-dire les éléments sans club).
 */
final class ClubReach
{
    /** @return array<int, int>|null null = tous */
    public static function ids(User $user): ?array
    {
        return ActivityAccess::manageableClubIds($user);
    }

    public static function isGlobal(User $user): bool
    {
        return self::ids($user) === null;
    }

    public static function allows(User $user, ?int $clubId): bool
    {
        $ids = self::ids($user);

        return $ids === null || ($clubId !== null && in_array($clubId, $ids, true));
    }

    public static function authorize(User $user, ?int $clubId): void
    {
        abort_unless(self::allows($user, $clubId), 403, 'Cet élément est hors de votre périmètre.');
    }

    /** Refuse un club hors portée dans un formulaire (erreur de validation sur $field). */
    public static function validate(User $user, ?int $clubId, string $field = 'club_id'): void
    {
        if (! self::allows($user, $clubId)) {
            throw ValidationException::withMessages([$field => $clubId === null ? 'Choisissez un club.' : 'Ce club est hors de votre périmètre.']);
        }
    }

    /** Restreint une requête aux clubs de l'utilisateur (la Fondation, sans club, n'est visible que des administrateurs globaux). */
    public static function scope(Builder $query, User $user, string $column = 'club_id'): Builder
    {
        $ids = self::ids($user);

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /**
     * Clubs actifs de la portée de l'utilisateur.
     *
     * @return Collection<int, OrgNode>
     */
    public static function clubs(User $user): Collection
    {
        return ActivityAccess::manageableClubs($user)->get(['id', 'title', 'status', 'category'])
            ->filter(fn (OrgNode $club): bool => ($club->status ?? 'active') === 'active')->values();
    }

    /** Clubs sous forme id => titre, pour les listes déroulantes. */
    public static function options(User $user): array
    {
        return self::clubs($user)->pluck('title', 'id')->all();
    }
}
