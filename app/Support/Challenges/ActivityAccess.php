<?php

namespace App\Support\Challenges;

use App\Models\ClubActivity;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\MyOffice;
use App\Support\OrgAccess;
use Illuminate\Database\Eloquent\Builder;

/** Qui peut gérer les activités d'un club, et qui peut s'inscrire à une activité selon son public. */
final class ActivityAccess
{
    public const PERMISSION = 'event.manage';

    /**
     * Clubs dont l'utilisateur gère les activités : null = tous (droits globaux), sinon les identifiants.
     *
     * @return array<int, int>|null
     */
    public static function manageableClubIds(User $user): ?array
    {
        $responsibility = OrgAccess::responsibility($user, self::PERMISSION);

        if ($responsibility['all']) {
            return null;
        }

        $ids = $responsibility['clubs'];

        if ($responsibility['scopes'] !== []) {
            $ids = array_merge($ids, OrgNode::query()->where('kind', 'club')->whereIn('scope', $responsibility['scopes'])->pluck('id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public static function canManageClub(User $user, int $clubId): bool
    {
        $ids = self::manageableClubIds($user);

        return $ids === null || in_array($clubId, $ids, true);
    }

    /** Clubs actifs (ou tous si droits globaux) que l'utilisateur peut représenter. */
    public static function manageableClubs(User $user): Builder
    {
        $ids = self::manageableClubIds($user);

        return OrgNode::query()->where('kind', 'club')
            ->when($ids !== null, fn (Builder $query) => $query->whereIn('id', $ids))
            ->orderBy('title');
    }

    /** Peut gérer l'activité : un des clubs organisateurs lui est ouvert. */
    public static function canManageActivity(User $user, ClubActivity $activity): bool
    {
        $ids = self::manageableClubIds($user);

        return $ids === null || array_intersect($activity->organizerClubIds(), $ids) !== [];
    }

    /**
     * Blocs de l'organigramme du membre et leurs ancêtres : adhésions validées et postes occupés.
     *
     * @return array<int, int>
     */
    public static function nodeIds(User $user): array
    {
        $own = Membership::query()->where('user_id', $user->id)->where('status', 'approved')->whereNotNull('node_id')->pluck('node_id')
            ->merge(MyOffice::heldNodeIds($user))->map(fn ($id): int => (int) $id)->unique();

        if ($own->isEmpty()) {
            return [];
        }

        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $all = [];

        foreach ($own as $id) {
            for ($guard = 0; $id !== null && $guard < 80 && ! isset($all[$id]); $guard++) {
                $all[$id] = $id;
                $id = isset($parents[$id]) ? (int) $parents[$id] : null;
            }
        }

        return array_values($all);
    }

    /** Tunimateur : membre validé d'au moins un périmètre ou titulaire d'un poste. */
    public static function isTunimateur(User $user): bool
    {
        return Membership::query()->where('user_id', $user->id)->where('status', 'approved')->exists() || MyOffice::heldNodeIds($user) !== [];
    }

    /** Motif pour lequel le membre ne peut pas s'inscrire ; null s'il le peut. */
    public static function ineligibleReason(User $user, ClubActivity $activity): ?string
    {
        return match ($activity->audience) {
            'public' => null,
            'tunimateurs' => self::isTunimateur($user) ? null : 'Cette activité est réservée aux Tunimateurs (membres validés de la Fondation).',
            'club' => array_intersect($activity->organizerClubIds(), self::nodeIds($user)) !== [] ? null : 'Cette activité est réservée aux membres du club organisateur.',
            'department' => $activity->department_id !== null && in_array((int) $activity->department_id, self::nodeIds($user), true) ? null : 'Cette activité est réservée aux membres d’un département du club.',
            default => 'Inscription indisponible.',
        };
    }
}
