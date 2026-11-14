<?php

namespace App\Support\OrgChart;

use App\Models\ClubTemplateSlot;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Modèle d'équipe de chaque type de club (University, High School, We Are Carthage) : départements, postes, capacités
 * et niveaux d'accès. Il se règle en base (Admin > Modèles de clubs) et tous les clubs du type le suivent :
 * `sync()` crée les blocs manquants, renomme, aligne droits et rattachements. Les blocs ajoutés à la main dans
 * un club (sans rôle) ne sont jamais touchés, et un poste retiré du modèle qui a des titulaires est conservé.
 */
final class ClubTemplates
{
    /** Type de club (catégorie de l'organigramme) => libellé. */
    public const CATEGORIES = [
        'university_club' => 'University',
        'highschool_club' => 'High School',
        'regional_club' => 'We Are Carthage',
    ];

    /** Un poste de club ne peut pas porter de droits globaux ni d'entité : ceux-ci se donnent aux postes de direction. */
    public const ALLOWED_LEVELS = ['none', 'manager', 'club_president', 'moderator'];

    public const KINDS = ['position' => 'Poste', 'club_department' => 'Département'];

    private const DEPARTMENTS = ['marketing' => 'Marketing', 'hr' => 'RH', 'events' => 'Events', 'rd' => 'R&D'];

    /**
     * Modèle par défaut : un président, 4 départements (Marketing, RH, Events, R&D) avec leurs VP et, pour University /
     * High School, un assistant et des membres par département.
     *
     * @return array<string, array{title: string, parent: ?string, kind: string, capacity: string, access: string}>
     */
    public static function defaults(string $category): array
    {
        $regional = $category === 'regional_club';
        $slot = static fn (string $title, ?string $parent, string $kind = 'position', string $capacity = 'single', string $access = 'none'): array => [
            'title' => $title, 'parent' => $parent, 'kind' => $kind, 'capacity' => $capacity, 'access' => $access,
        ];

        $slots = ['president' => $slot($regional ? 'Président du club régional' : 'Président de club', null, 'position', 'single', 'club_president')];

        foreach (self::DEPARTMENTS as $slug => $label) {
            $slots['dept-'.$slug] = $slot('Département '.$label, 'president', 'club_department');
            $slots['vp-'.$slug] = $slot(($regional ? 'Vice-président ' : 'VP ').$label, 'dept-'.$slug);

            if (! $regional) {
                $slots['assistant-'.$slug] = $slot('Assistant '.$label, 'dept-'.$slug);
                $slots['members-'.$slug] = $slot('Membres '.$label, 'dept-'.$slug, 'position', 'multiple');
            }
        }

        if ($regional) {
            $slots['members'] = $slot('Assistants et membres', 'president', 'position', 'multiple');
        }

        return $slots;
    }

    public static function ready(): bool
    {
        return Schema::hasTable('club_template_slots');
    }

    /**
     * Crée le modèle par défaut d'un type de club ; $levels remplace les niveaux d'accès de certains postes (clé => niveau).
     *
     * @param array<string, string> $levels
     */
    public static function seed(string $category, array $levels = []): void
    {
        $order = 0;

        foreach (self::defaults($category) as $key => $definition) {
            $level = $levels[$key] ?? $definition['access'];

            ClubTemplateSlot::query()->updateOrCreate(['category' => $category, 'key' => $key], [
                'parent_key' => $definition['parent'],
                'kind' => $definition['kind'],
                'title' => $definition['title'],
                'capacity' => $definition['capacity'],
                'access_level' => $definition['kind'] === 'position' && in_array($level, self::ALLOWED_LEVELS, true) ? $level : 'none',
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * Postes et départements du modèle, dans l'ordre de l'arbre (un parent toujours avant ses enfants).
     *
     * @return array<string, array{id: ?int, title: string, parent: ?string, kind: string, capacity: string, access: string, depth: int}>
     */
    public static function slots(string $category): array
    {
        if (! isset(self::CATEGORIES[$category])) {
            return [];
        }

        if (! self::ready()) {
            return self::flatten(self::defaults($category), array_map(static fn (): ?int => null, self::defaults($category)));
        }

        $rows = ClubTemplateSlot::query()->where('category', $category)->orderBy('sort_order')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            self::seed($category);
            $rows = ClubTemplateSlot::query()->where('category', $category)->orderBy('sort_order')->orderBy('id')->get();
        }

        $definitions = [];
        $ids = [];

        foreach ($rows as $row) {
            $definitions[$row->key] = ['title' => $row->title, 'parent' => $row->parent_key, 'kind' => $row->kind, 'capacity' => $row->capacity, 'access' => $row->access_level];
            $ids[$row->key] = $row->id;
        }

        return self::flatten($definitions, $ids);
    }

    /**
     * @param array<string, array{title: string, parent: ?string, kind: string, capacity: string, access: string}> $definitions
     * @param array<string, ?int> $ids
     * @return array<string, array{id: ?int, title: string, parent: ?string, kind: string, capacity: string, access: string, depth: int}>
     */
    private static function flatten(array $definitions, array $ids): array
    {
        $children = [];

        foreach ($definitions as $key => $definition) {
            $parent = $definition['parent'] !== null && isset($definitions[$definition['parent']]) ? $definition['parent'] : null;
            $children[$parent ?? ''][] = $key;
        }

        $ordered = [];
        $walk = function (string $parent, int $depth) use (&$walk, &$ordered, $children, $definitions, $ids): void {
            foreach ($children[$parent] ?? [] as $key) {
                if (isset($ordered[$key])) {
                    continue;
                }

                $ordered[$key] = $definitions[$key] + ['id' => $ids[$key] ?? null, 'depth' => $depth];
                $walk($key, $depth + 1);
            }
        };
        $walk('', 0);

        return $ordered;
    }

    /* ------------------------------------------------------------------ Édition du modèle */

    /**
     * Rend la clé d'un nouveau bloc : unique dans le type de club, 40 caractères au plus.
     */
    public static function newKey(string $category, string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'poste', 30, '');
        $key = $base;

        for ($i = 2; ClubTemplateSlot::query()->where('category', $category)->where('key', $key)->exists(); $i++) {
            $key = $base.'-'.$i;
        }

        return $key;
    }

    /**
     * Sous-blocs (directs ou non) d'un bloc du modèle.
     *
     * @return array<int, string>
     */
    public static function descendantKeys(string $category, string $key): array
    {
        $slots = self::slots($category);
        $found = [];
        $queue = [$key];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($slots as $slotKey => $slot) {
                if ($slot['parent'] === $current && ! in_array($slotKey, $found, true)) {
                    $found[] = $slotKey;
                    $queue[] = $slotKey;
                }
            }
        }

        return $found;
    }

    /* ------------------------------------------------------------------ Application aux clubs */

    /**
     * Aligne les clubs sur le modèle de leur type (tous, ou un seul club) et rend le nombre de blocs créés,
     * modifiés ou retirés.
     */
    public function sync(?string $category = null, ?OrgNode $only = null): int
    {
        return DB::transaction(function () use ($category, $only): int {
            $categories = $category !== null ? [$category] : array_keys(self::CATEGORIES);
            $clubs = $only !== null
                ? collect([$only])
                : OrgNode::query()->where('kind', 'club')->whereIn('category', $categories)->get();

            if ($clubs->isEmpty()) {
                return 0;
            }

            $byParent = null;
            $counts = null;

            if ($only === null) {
                $byParent = OrgNode::query()->get()->groupBy('parent_id');
                $counts = OrgAssignment::query()->selectRaw('node_id, count(*) as total')->groupBy('node_id')->pluck('total', 'node_id');
            }

            $templates = [];
            $changes = 0;

            foreach ($clubs as $club) {
                $slots = $templates[$club->category] ??= self::slots((string) $club->category);

                if ($slots === []) {
                    continue;
                }

                $team = $only !== null ? $this->subtree($club) : $this->branch($club->id, $byParent);
                $held = $only !== null
                    ? OrgAssignment::query()->whereIn('node_id', $team->pluck('id'))->selectRaw('node_id, count(*) as total')->groupBy('node_id')->pluck('total', 'node_id')
                    : $counts;

                $changes += $this->syncClub($club, $slots, $team, $held);
            }

            return $changes;
        });
    }

    /** Blocs sous un club, requête par niveau (un seul club). */
    private function subtree(OrgNode $club): Collection
    {
        $found = collect();
        $ids = [$club->id];

        for ($depth = 0; $ids !== [] && $depth < 12; $depth++) {
            $level = OrgNode::query()->whereIn('parent_id', $ids)->get();
            $found = $found->concat($level);
            $ids = $level->pluck('id')->all();
        }

        return $found;
    }

    /** Blocs sous un club, depuis un regroupement déjà chargé. */
    private function branch(int $clubId, Collection $byParent): Collection
    {
        $found = collect();
        $queue = [$clubId];

        while ($queue !== []) {
            foreach ($byParent[array_shift($queue)] ?? [] as $child) {
                $found->push($child);
                $queue[] = $child->id;
            }
        }

        return $found;
    }

    /**
     * @param array<string, array{id: ?int, title: string, parent: ?string, kind: string, capacity: string, access: string, depth: int}> $slots
     * @param Collection<int, OrgNode> $team
     * @param Collection<int|string, int|string>|array<int, int> $held nombre de titulaires par bloc
     */
    private function syncClub(OrgNode $club, array $slots, Collection $team, mixed $held): int
    {
        $changes = 0;
        $holders = fn (int $id): int => (int) ($held[$id] ?? 0);
        $parentOf = $team->pluck('parent_id', 'id')->all();

        // 1. Les blocs sans rôle dont le titre est celui d'un bloc du modèle en prennent le rôle.
        $byTitle = [];

        foreach ($slots as $key => $slot) {
            $byTitle[$slot['kind'].'|'.mb_strtolower($slot['title'])] = $key;
        }

        $taken = $team->pluck('role_key')->filter()->flip()->all();

        foreach ($team as $node) {
            if ($node->role_key !== null || ! in_array($node->kind, ['position', 'club_department'], true)) {
                continue;
            }

            $key = $byTitle[$node->kind.'|'.mb_strtolower((string) $node->title)] ?? null;

            if ($key === null || isset($taken[$key])) {
                continue;
            }

            $node->forceFill(['role_key' => $key])->saveQuietly();
            $taken[$key] = true;
            $changes++;
        }

        // 2. Rang de chaque bloc parmi ses frères du modèle.
        $rank = [];
        $siblings = [];

        foreach ($slots as $key => $slot) {
            $rank[$key] = $siblings[$slot['parent'] ?? ''] = ($siblings[$slot['parent'] ?? ''] ?? -1) + 1;
        }

        // 3. Création, renommage, droits, capacité, rattachement.
        $byKey = $team->whereNotNull('role_key')->keyBy('role_key')->all();

        foreach ($slots as $key => $slot) {
            $parentId = $slot['parent'] !== null && isset($byKey[$slot['parent']]) ? $byKey[$slot['parent']]->id : $club->id;
            $node = $byKey[$key] ?? null;

            if ($node === null) {
                $node = OrgNode::query()->create([
                    'parent_id' => $parentId,
                    'relation_type' => 'hierarchy',
                    'kind' => $slot['kind'],
                    'capacity' => $slot['capacity'],
                    'access_level' => $slot['kind'] === 'position' ? $slot['access'] : 'none',
                    'role_key' => $key,
                    'category' => $club->category,
                    'title' => $slot['title'],
                    'subtitle' => $club->title,
                    'sort_order' => $rank[$key],
                ]);
                $byKey[$key] = $node;
                $parentOf[$node->id] = $parentId;
                $changes++;

                continue;
            }

            $attributes = [];

            if ($node->title !== $slot['title']) {
                $attributes['title'] = $slot['title'];
            }

            if ($node->kind === 'position') {
                if ($node->access_level !== $slot['access']) {
                    $attributes['access_level'] = $slot['access'];
                }

                if ($node->capacity !== $slot['capacity'] && ($slot['capacity'] === 'multiple' || $holders($node->id) <= 1)) {
                    $attributes['capacity'] = $slot['capacity'];
                }
            }

            if ((int) $node->parent_id !== $parentId && ! $this->isUnder($parentId, $node->id, $parentOf)) {
                $attributes['parent_id'] = $parentId;
            }

            if ((int) $node->sort_order !== $rank[$key]) {
                $attributes['sort_order'] = $rank[$key];
            }

            if ($attributes !== []) {
                $node->forceFill($attributes)->saveQuietly();
                $parentOf[$node->id] = (int) $node->parent_id;
                $changes++;
            }
        }

        // 4. Blocs dont le rôle n'existe plus dans le modèle : retirés s'ils sont vides, sinon libérés (ils restent, sans rôle).
        $orphans = $team->filter(fn (OrgNode $node): bool => $node->role_key !== null && ! isset($slots[$node->role_key]))->values();

        for ($pass = 0; $pass < 6 && $orphans->isNotEmpty(); $pass++) {
            foreach ($orphans as $node) {
                if ($holders($node->id) > 0 || in_array($node->id, $parentOf, true)) {
                    continue;
                }

                OrgNode::query()->whereKey($node->id)->delete();
                unset($parentOf[$node->id]);
                $orphans = $orphans->reject(fn (OrgNode $other): bool => $other->id === $node->id)->values();
                $changes++;
            }
        }

        foreach ($orphans as $node) {
            $node->forceFill(['role_key' => null])->saveQuietly();
            $changes++;
        }

        // 5. Ancien bloc fourre-tout du modèle précédent, vide.
        foreach ($team->where('title', 'Autres membres et responsables') as $others) {
            if (array_key_exists($others->id, $parentOf) && $holders($others->id) === 0 && ! in_array($others->id, $parentOf, true)) {
                OrgNode::query()->whereKey($others->id)->delete();
                unset($parentOf[$others->id]);
                $changes++;
            }
        }

        return $changes;
    }

    /** Vrai si $candidate se trouve dans la descendance de $nodeId (donc le choisir comme parent ferait un cycle). */
    private function isUnder(int $candidate, int $nodeId, array $parentOf): bool
    {
        for ($id = $candidate, $guard = 0; $id !== null && $guard < 60; $guard++) {
            if ($id === $nodeId) {
                return true;
            }

            $id = isset($parentOf[$id]) ? (int) $parentOf[$id] : null;
        }

        return false;
    }
}
