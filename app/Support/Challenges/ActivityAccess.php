<?php

namespace App\Support\Challenges;

use App\Models\ClubActivity;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\MyOffice;
use App\Support\OrgAccess;
use App\Support\OrgScopes;
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

    /**
     * Périmètres de l'utilisateur : principal et secondaires de ses blocs d'organigramme (adhésions, postes, parents),
     * périmètre de ses adhésions validées, clubs qu'il gère et périmètres dont il a la responsabilité. null = tous (droits globaux).
     *
     * @return array<int, string>|null
     */
    public static function scopesOf(User $user): ?array
    {
        $responsibility = OrgAccess::responsibility($user, self::PERMISSION);

        if ($responsibility['all']) {
            return null;
        }

        $nodeIds = array_merge(self::nodeIds($user), array_map('intval', $responsibility['clubs']));
        $scopes = [];

        if ($nodeIds !== []) {
            foreach (OrgNode::query()->whereIn('id', $nodeIds)->get(['id', 'scope', 'extra_scopes']) as $node) {
                $scopes[] = $node->scope;
                array_push($scopes, ...array_values((array) $node->extra_scopes));
            }
        }

        $scopes = array_merge(
            $scopes,
            Membership::query()->where('user_id', $user->id)->where('status', 'approved')->whereNotNull('scope')->pluck('scope')->all(),
        );

        $scopes = array_values(array_unique(array_map('strval', array_filter($scopes, fn ($scope): bool => is_string($scope) && $scope !== ''))));

        return array_values(array_unique(array_merge($scopes, OrgScopes::expandManaged($responsibility['scopes']))));
    }

    /** Tunimateur : membre validé d'au moins un périmètre ou titulaire d'un poste. */
    public static function isTunimateur(User $user): bool
    {
        return Membership::query()->where('user_id', $user->id)->where('status', 'approved')->exists() || MyOffice::heldNodeIds($user) !== [];
    }

    /**
     * Présidents d'un club : titulaires, en ce moment, d'un poste de niveau « président de club » situé dans ce club.
     *
     * @return array<int, int>
     */
    public static function presidentIds(int $clubId): array
    {
        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $positions = OrgNode::query()->where('kind', 'position')->where('access_level', 'club_president')->pluck('id')
            ->filter(fn ($id): bool => self::isInside((int) $id, $clubId, $parents))->all();

        if ($positions === []) {
            return [];
        }

        return OrgAssignment::query()
            ->whereIn('node_id', $positions)
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
            ->with('person:id,user_id')
            ->get()
            ->map(fn (OrgAssignment $assignment): ?int => $assignment->person?->user_id !== null ? (int) $assignment->person->user_id : null)
            ->filter()->unique()->values()->all();
    }

    /** Valide le compte rendu : le président du club porteur (à défaut de président, un administrateur à droits globaux). */
    public static function canValidateReport(User $user, ClubActivity $activity): bool
    {
        $ids = self::presidentIds((int) $activity->club_id);

        if (in_array($user->id, $ids, true)) {
            return true;
        }

        return $ids === [] && self::manageableClubIds($user) === null;
    }

    /**
     * Membres qui peuvent figurer dans la partie RH : adhérents validés et titulaires d'un poste des clubs organisateurs (départements compris).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function reportCandidates(ClubActivity $activity): \Illuminate\Support\Collection
    {
        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $clubIds = $activity->organizerClubIds();
        $nodeIds = array_values(array_filter(array_map('intval', array_keys($parents)), function (int $id) use ($clubIds, $parents): bool {
            foreach ($clubIds as $clubId) {
                if (self::isInside($id, $clubId, $parents) || $id === $clubId) {
                    return true;
                }
            }

            return false;
        }));

        $userIds = Membership::query()->where('status', 'approved')->whereIn('node_id', $nodeIds)->pluck('user_id')
            ->merge(OrgAssignment::query()
                ->whereIn('node_id', $nodeIds)
                ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString()))
                ->with('person:id,user_id')->get()->map(fn (OrgAssignment $assignment) => $assignment->person?->user_id))
            ->filter()->unique()->values();

        return User::query()->whereIn('id', $userIds)->orderBy('name')->get(['id', 'name']);
    }

    /** @param array<int, int|null> $parents */
    private static function isInside(int $nodeId, int $ancestorId, array $parents): bool
    {
        $id = $parents[$nodeId] ?? null;

        for ($guard = 0; $id !== null && $guard < 80; $guard++) {
            if ((int) $id === $ancestorId) {
                return true;
            }

            $id = $parents[$id] ?? null;
        }

        return false;
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
