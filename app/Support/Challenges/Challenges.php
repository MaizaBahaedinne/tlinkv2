<?php

namespace App\Support\Challenges;

use App\Models\OrgNode;
use App\Support\ModuleCatalog;
use Illuminate\Support\Facades\Schema;

/** Défis et activités de clubs : module ouvert si actif et migré. */
final class Challenges
{
    public const MODULE = 'challenges.challenges';

    private static ?bool $ready = null;

    public static function enabled(): bool
    {
        return (self::$ready ??= Schema::hasTable('challenges')) && ModuleCatalog::isActive(self::MODULE);
    }

    /**
     * Départements de club (public « département du club ») regroupés par club.
     *
     * @return array<int, array<int, string>> identifiant du club => [identifiant du département => titre]
     */
    public static function departmentsByClub(): array
    {
        $nodes = OrgNode::query()->get(['id', 'parent_id', 'kind', 'title'])->keyBy('id');
        $byClub = [];

        foreach ($nodes as $node) {
            if ($node->kind !== 'club_department') {
                continue;
            }

            $parent = $node->parent_id !== null ? $nodes->get($node->parent_id) : null;

            for ($guard = 0; $parent !== null && $guard < 80; $guard++) {
                if ($parent->kind === 'club') {
                    $byClub[$parent->id][$node->id] = $node->title;
                    break;
                }

                $parent = $parent->parent_id !== null ? $nodes->get($parent->parent_id) : null;
            }
        }

        foreach ($byClub as &$departments) {
            asort($departments);
        }

        return $byClub;
    }
}
