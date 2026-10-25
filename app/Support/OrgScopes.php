<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Perimetres de la fondation : ils remplacent l'ancienne table `entities`.
 * Un bloc de l'organigramme porte un perimetre (ou en herite) ; les adhesions, publications et
 * evenements memorisent le perimetre auquel ils appartiennent.
 */
final class OrgScopes
{
    public const DEFAULT = [
        'foundation' => 'Fondation',
        'university' => 'University',
        'high-school' => 'High School',
        'alumni' => 'Alumni',
    ];

    public const CORE = ['foundation', 'university', 'high-school', 'alumni'];

    /** Branche de clubs de l'organigramme par perimetre : perimetre => [cle systeme, categorie]. */
    public const CLUB_BRANCHES = [
        'university' => ['university-clubs', 'university_club'],
        'high-school' => ['highschool-clubs', 'highschool_club'],
    ];

    /** @var array<string, string|null>|null */
    private static ?array $hierarchyCache = null;

    /** @var array<string, string>|null */
    private static ?array $catalogCache = null;

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$catalogCache !== null) {
            return self::$catalogCache;
        }

        if (!Schema::hasTable('settings')) {
            self::$catalogCache = self::DEFAULT;

            return self::$catalogCache;
        }

        $setting = Setting::query()->where('group', 'org')->where('key', 'scopes_catalog')->value('value');
        $decoded = is_string($setting) ? json_decode($setting, true) : null;

        if (!is_array($decoded)) {
            self::$catalogCache = self::DEFAULT;

            return self::$catalogCache;
        }

        $catalog = [];

        foreach ($decoded as $slug => $label) {
            $slug = (string) $slug;
            if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]$/', $slug)) {
                continue;
            }

            $text = trim((string) $label);
            if ($text === '') {
                continue;
            }

            $catalog[$slug] = $text;
        }

        foreach (self::DEFAULT as $slug => $label) {
            $catalog[$slug] = $catalog[$slug] ?? $label;
        }

        self::$catalogCache = $catalog;

        return self::$catalogCache;
    }

    public static function valid(?string $scope): bool
    {
        return $scope !== null && isset(self::all()[$scope]);
    }

    public static function label(?string $scope): ?string
    {
        return $scope === null ? null : (self::all()[$scope] ?? $scope);
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /**
     * Type d'etablissement d'un club selon son perimetre (null : aucun etablissement).
     */
    public static function institutionType(?string $scope): ?string
    {
        return match ($scope) {
            'university' => 'university',
            'high-school' => 'high_school',
            default => null,
        };
    }

    /**
     * @return array<string, string|null>
     */
    public static function hierarchy(): array
    {
        if (self::$hierarchyCache !== null) {
            return self::$hierarchyCache;
        }

        $default = [
            'foundation' => null,
        ];

        foreach (self::slugs() as $slug) {
            $default[$slug] = $default[$slug] ?? ($slug === 'foundation' ? null : 'foundation');
        }

        if (!Schema::hasTable('settings')) {
            self::$hierarchyCache = $default;

            return self::$hierarchyCache;
        }

        $setting = Setting::query()->where('group', 'org')->where('key', 'scope_hierarchy')->value('value');
        $decoded = is_string($setting) ? json_decode($setting, true) : null;

        if (!is_array($decoded)) {
            self::$hierarchyCache = $default;

            return self::$hierarchyCache;
        }

        $all = self::slugs();
        $clean = [];

        foreach ($all as $slug) {
            $parent = $decoded[$slug] ?? $default[$slug] ?? null;
            $clean[$slug] = in_array($parent, $all, true) ? $parent : null;
            if ($clean[$slug] === $slug) {
                $clean[$slug] = null;
            }
        }

        if (!self::isAcyclic($clean)) {
            self::$hierarchyCache = $default;

            return self::$hierarchyCache;
        }

        self::$hierarchyCache = $clean;

        return self::$hierarchyCache;
    }

    public static function flushCache(): void
    {
        self::$hierarchyCache = null;
        self::$catalogCache = null;
    }

    /**
     * @param array<string, string|null> $hierarchy
     */
    public static function isAcyclic(array $hierarchy): bool
    {
        $visited = [];
        $active = [];

        foreach (array_keys($hierarchy) as $slug) {
            if (!self::dfsAcyclic($slug, $hierarchy, $visited, $active)) {
                return false;
            }
        }

        return true;
    }

    public static function covers(string $grantedScope, string $targetScope): bool
    {
        if ($grantedScope === $targetScope) {
            return true;
        }

        $hierarchy = self::hierarchy();
        $current = $targetScope;

        for ($guard = 0; $guard < 20; $guard++) {
            $parent = $hierarchy[$current] ?? null;

            if ($parent === null) {
                return false;
            }

            if ($parent === $grantedScope) {
                return true;
            }

            $current = $parent;
        }

        return false;
    }

    /**
     * @param array<int, string> $scopes
     * @return array<int, string>
     */
    public static function expandManaged(array $scopes): array
    {
        $all = self::slugs();
        $set = [];

        foreach ($all as $target) {
            foreach ($scopes as $granted) {
                if (self::covers((string) $granted, $target)) {
                    $set[$target] = true;
                    break;
                }
            }
        }

        return array_keys($set);
    }

    /**
     * @param array<string, string|null> $hierarchy
     * @param array<string, bool> $visited
     * @param array<string, bool> $active
     */
    private static function dfsAcyclic(string $node, array $hierarchy, array &$visited, array &$active): bool
    {
        if (($active[$node] ?? false) === true) {
            return false;
        }

        if (($visited[$node] ?? false) === true) {
            return true;
        }

        $active[$node] = true;
        $parent = $hierarchy[$node] ?? null;

        if (is_string($parent) && $parent !== '' && isset($hierarchy[$parent]) && !self::dfsAcyclic($parent, $hierarchy, $visited, $active)) {
            return false;
        }

        $active[$node] = false;
        $visited[$node] = true;

        return true;
    }
}
