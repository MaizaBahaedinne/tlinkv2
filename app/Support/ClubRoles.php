<?php

namespace App\Support;

use App\Models\OrgNode;
use App\Models\Setting;
use App\Support\OrgChart\OrgChartInstaller;

/**
 * Rôles de l'équipe type des clubs University / High School (Président, VP / Assistant / Membres par département).
 * Chaque rôle porte un niveau d'accès unique, identique dans tous les clubs : il se définit ici, une fois.
 */
class ClubRoles
{
    public const CATEGORIES = ['university_club', 'highschool_club'];

    /** Un poste de club ne peut pas porter de droits globaux ni d'entite : ceux-ci se donnent aux postes de direction. */
    public const ALLOWED_LEVELS = ['none', 'manager', 'club_president', 'moderator'];

    private const GROUP = 'org';

    private const KEY = 'club_role_access';

    /**
     * Rôles (postes uniquement) dans l'ordre de l'équipe type.
     *
     * @return array<string, array{title: string, department: ?string, capacity: string}>
     */
    public static function all(): array
    {
        $template = app(OrgChartInstaller::class)->clubTemplate(false);
        $roles = [];

        foreach ($template as $key => $definition) {
            if ($definition['kind'] !== 'position') {
                continue;
            }

            $parent = $definition['parent'];

            $roles[$key] = [
                'title' => $definition['title'],
                'department' => $parent && isset($template[$parent]) && $template[$parent]['kind'] === 'club_department' ? $template[$parent]['title'] : null,
                'capacity' => $definition['capacity'],
            ];
        }

        return $roles;
    }

    /**
     * Rôle (clé) d'un titre de l'équipe type, tous types de bloc confondus.
     *
     * @return array<string, string> titre => clé
     */
    public static function keysByTitle(): array
    {
        $map = [];

        foreach (app(OrgChartInstaller::class)->clubTemplate(false) as $key => $definition) {
            $map[$definition['title']] = $key;
        }

        return $map;
    }

    /**
     * @return array<string, string> clé du rôle => niveau d'accès
     */
    public static function levels(): array
    {
        $stored = json_decode((string) Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->value('value'), true);
        $stored = is_array($stored) ? $stored : [];
        $levels = [];

        foreach (self::all() as $key => $role) {
            $level = $stored[$key] ?? ($key === 'president' ? 'club_president' : 'none');
            $levels[$key] = in_array($level, self::ALLOWED_LEVELS, true) ? $level : 'none';
        }

        return $levels;
    }

    public static function level(string $key): string
    {
        return self::levels()[$key] ?? 'none';
    }

    /**
     * Enregistre les niveaux et les applique aux postes de tous les clubs.
     *
     * @param array<string, string> $levels
     * @return int nombre de postes mis à jour
     */
    public static function save(array $levels): int
    {
        $levels = array_intersect_key($levels, self::all());

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode(array_merge(self::levels(), $levels))],
        );

        return self::apply();
    }

    /**
     * Aligne le niveau d'accès de chaque poste à rôle sur celui défini pour son rôle.
     */
    public static function apply(): int
    {
        $count = 0;

        foreach (self::levels() as $key => $level) {
            $count += OrgNode::query()
                ->where('kind', 'position')
                ->where('role_key', $key)
                ->whereIn('category', self::CATEGORIES)
                ->where('access_level', '!=', $level)
                ->update(['access_level' => $level]);
        }

        return $count;
    }
}
