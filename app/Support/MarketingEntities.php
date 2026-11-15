<?php

namespace App\Support;

use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\Challenges\ActivityAccess;
use Illuminate\Support\Collection;

/**
 * Entités qui ont leurs pages et leurs comptes sur les réseaux sociaux : la Fondation, les chapitres, les projets
 * (périmètres de l'organigramme) et les clubs. Une entité s'identifie par une clé : « club:12 » ou « scope:university ».
 */
final class MarketingEntities
{
    public const TYPES = ['foundation' => 'Fondation', 'chapter' => 'Chapitre', 'project' => 'Projet', 'club' => 'Club'];

    private const PERMISSION = 'event.manage';

    /** @var Collection<string, array<string, mixed>>|null */
    private static ?Collection $cache = null;

    private static ?int $cacheApp = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function keyOf(?int $clubId, ?string $scope): string
    {
        return $clubId !== null ? 'club:'.$clubId : 'scope:'.($scope ?: 'foundation');
    }

    /**
     * Toutes les entités actives : Fondation, chapitres, projets puis clubs.
     *
     * @return Collection<string, array{key: string, type: string, type_label: string, label: string, club_id: int|null, scope: string|null}>
     */
    public static function all(): Collection
    {
        // Mémorisé le temps d'une requête (et d'un test) ; vidé dès qu'un bloc de l'organigramme change.
        if (self::$cache !== null && self::$cacheApp === spl_object_id(app())) {
            return self::$cache;
        }

        $entities = collect();
        $scopes = collect(OrgScopes::all())->map(function (string $label, string $slug): array {
            $type = $slug === 'foundation' ? 'foundation' : (OrgScopes::type($slug) === 'chapter' ? 'chapter' : 'project');

            return ['type' => $type, 'label' => $label, 'slug' => $slug];
        });

        foreach (['foundation', 'chapter', 'project'] as $type) {
            foreach ($scopes->where('type', $type)->sortBy('label') as $item) {
                $key = self::keyOf(null, $item['slug']);
                $entities[$key] = ['key' => $key, 'type' => $type, 'type_label' => self::TYPES[$type], 'label' => (string) $item['label'], 'club_id' => null, 'scope' => $item['slug']];
            }
        }

        foreach (OrgNode::query()->where('kind', 'club')->orderBy('title')->get(['id', 'title', 'status']) as $club) {
            if (($club->status ?? 'active') !== 'active') {
                continue;
            }

            $key = self::keyOf((int) $club->id, null);
            $entities[$key] = ['key' => $key, 'type' => 'club', 'type_label' => self::TYPES['club'], 'label' => $club->title, 'club_id' => (int) $club->id, 'scope' => null];
        }

        self::$cacheApp = spl_object_id(app());

        return self::$cache = $entities;
    }

    /** @return array{key: string, type: string, type_label: string, label: string, club_id: int|null, scope: string|null}|null */
    public static function find(?string $key): ?array
    {
        return $key === null ? null : self::all()->get($key);
    }

    public static function label(?int $clubId, ?string $scope): string
    {
        return self::find(self::keyOf($clubId, $scope))['label'] ?? ($clubId !== null ? 'Club #'.$clubId : (OrgScopes::label($scope ?: 'foundation') ?? 'Fondation'));
    }

    /**
     * Entités que l'utilisateur gère (droit « événements ») : tout pour les administrateurs globaux, sinon son club
     * et les chapitres, projets et clubs de ses périmètres.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function reach(User $user): Collection
    {
        $responsibility = OrgAccess::responsibility($user, self::PERMISSION);

        if ($responsibility['all']) {
            return self::all();
        }

        $clubs = ActivityAccess::manageableClubIds($user) ?? [];

        return self::all()->filter(fn (array $entity): bool => $entity['club_id'] !== null
            ? in_array($entity['club_id'], $clubs, true)
            : in_array($entity['scope'], $responsibility['scopes'], true));
    }

    public static function allows(User $user, ?int $clubId, ?string $scope): bool
    {
        return self::reach($user)->has(self::keyOf($clubId, $scope));
    }

    /**
     * Restreint une requête (colonnes club_id et scope) aux entités de l'utilisateur.
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder
     * @param TBuilder $query
     * @return TBuilder
     */
    public static function restrict($query, User $user)
    {
        $responsibility = OrgAccess::responsibility($user, self::PERMISSION);

        if ($responsibility['all']) {
            return $query;
        }

        $clubs = ActivityAccess::manageableClubIds($user) ?? [];
        $scopes = $responsibility['scopes'];

        return $query->where(fn ($inner) => $inner->whereIn('club_id', $clubs)
            ->orWhere(fn ($foundation) => $foundation->whereNull('club_id')->whereIn('scope', $scopes)));
    }

    /**
     * Entités regroupées par type, pour les listes déroulantes : libellé du type => [clé => libellé].
     *
     * @param Collection<string, array<string, mixed>> $entities
     * @return array<string, array<string, string>>
     */
    public static function grouped(Collection $entities): array
    {
        $groups = [];

        foreach ($entities as $entity) {
            $groups[$entity['type_label']][$entity['key']] = $entity['label'];
        }

        return $groups;
    }

    /**
     * Personnes qui peuvent demander un accès pour cette entité : adhérents validés et titulaires de postes d'un club,
     * titulaires des postes d'un chapitre, d'un projet ou de la Fondation (hors postes de clubs).
     *
     * @return Collection<int, User>
     */
    public static function people(string $key): Collection
    {
        $entity = self::find($key);

        if ($entity === null) {
            return collect();
        }

        if ($entity['club_id'] !== null) {
            return ActivityAccess::candidatesForClubs([$entity['club_id']]);
        }

        $parents = OrgNode::query()->pluck('parent_id', 'id')->all();
        $kinds = OrgNode::query()->pluck('kind', 'id')->all();
        $scopes = OrgAccess::scopeMap();
        $nodeIds = [];

        foreach (array_keys($parents) as $id) {
            if (($scopes[$id] ?? null) === $entity['scope'] && ($kinds[$id] ?? null) === 'position' && ! self::insideClub((int) $id, $parents, $kinds)) {
                $nodeIds[] = (int) $id;
            }
        }

        $titles = OrgNode::query()->whereIn('id', $nodeIds)->pluck('title', 'id');
        $today = now()->toDateString();

        $assignments = OrgAssignment::query()
            ->whereIn('node_id', $nodeIds)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('person:id,user_id')
            ->get();

        $roles = [];

        foreach ($assignments as $assignment) {
            if ($assignment->person?->user_id !== null) {
                $roles[(int) $assignment->person->user_id][] = $titles[$assignment->node_id] ?? '';
            }
        }

        return User::query()->whereIn('id', array_keys($roles))->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->each(fn (User $user) => $user->setAttribute('report_role', implode(', ', array_unique(array_filter($roles[$user->id])))))
            ->values();
    }

    /** @param array<int, int|null> $parents @param array<int, string> $kinds */
    private static function insideClub(int $nodeId, array $parents, array $kinds): bool
    {
        $id = $parents[$nodeId] ?? null;

        for ($guard = 0; $id !== null && $guard < 80; $guard++) {
            if (($kinds[$id] ?? null) === 'club') {
                return true;
            }

            $id = $parents[$id] ?? null;
        }

        return false;
    }
}
