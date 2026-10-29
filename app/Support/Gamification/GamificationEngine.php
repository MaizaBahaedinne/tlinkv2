<?php

namespace App\Support\Gamification;

use App\Models\AppNotification;
use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Calcule l'avancement des membres sur les missions et décerne les badges.
 *
 * L'avancement n'est jamais stocké : il est recalculé depuis les données de la plateforme (voir ActivityCatalog),
 * seul le badge obtenu est conservé (user_badges) et le reste acquis même si l'activité diminue ensuite.
 */
final class GamificationEngine
{
    /**
     * Avancement d'un membre sur tous les badges actifs.
     *
     * @return array<int, array{badge: Badge, earned: bool, awarded_at: ?\Illuminate\Support\Carbon, percent: int, done: int, total: int, missions: array<int, array<string, mixed>>}>
     */
    public static function progress(int $userId): array
    {
        $awarded = UserBadge::query()->where('user_id', $userId)->pluck('awarded_at', 'badge_id');
        $counts = [];

        return self::activeBadges()->map(function (Badge $badge) use ($userId, $awarded, &$counts): array {
            return self::describe($badge, $userId, $awarded->get($badge->id), $counts);
        })->values()->all();
    }

    /**
     * Recalcule les badges d'un membre (uniquement ceux qui contiennent l'une des activités `$keys`, si fournies)
     * et décerne ceux dont toutes les missions sont réussies.
     *
     * @param  array<int, string>|null  $keys
     * @return Collection<int, Badge> badges décernés lors de cet appel
     */
    public static function refresh(int $userId, ?array $keys = null): Collection
    {
        $earnedIds = UserBadge::query()->where('user_id', $userId)->pluck('badge_id')->all();
        $counts = [];
        $newlyAwarded = collect();

        foreach (self::activeBadges() as $badge) {
            if (in_array($badge->id, $earnedIds, true)) {
                continue;
            }

            if ($keys !== null && ! self::usesAny($badge, $keys)) {
                continue;
            }

            $state = self::describe($badge, $userId, null, $counts);

            if ($state['total'] > 0 && $state['done'] === $state['total']) {
                $created = UserBadge::query()->firstOrCreate(
                    ['user_id' => $userId, 'badge_id' => $badge->id],
                    ['awarded_at' => now()],
                );

                if ($created->wasRecentlyCreated) {
                    self::notify($userId, $badge);
                    $newlyAwarded->push($badge);
                }
            }
        }

        return $newlyAwarded;
    }

    /**
     * Décerne directement un badge (fin de cours ou de module Academy), sans passer par des missions.
     * Renvoie true si le badge vient d'être obtenu.
     */
    public static function award(int $userId, Badge $badge): bool
    {
        if (! $badge->is_active) {
            return false;
        }

        $created = UserBadge::query()->firstOrCreate(
            ['user_id' => $userId, 'badge_id' => $badge->id],
            ['awarded_at' => now()],
        );

        if ($created->wasRecentlyCreated) {
            self::notify($userId, $badge);
        }

        return $created->wasRecentlyCreated;
    }

    /**
     * Version appelée depuis les observateurs de modèles : une erreur de gamification ne doit jamais bloquer l'action du membre.
     *
     * @param  array<int, string>|null  $keys
     */
    public static function refreshSafely(int $userId, ?array $keys = null): void
    {
        try {
            if (! Schema::hasTable('user_badges')) {
                return;
            }

            self::refresh($userId, $keys);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Recalcule tous les membres (après une modification des missions, ou pour rattraper l'historique).
     *
     * @return array{users: int, awarded: int}
     */
    public static function refreshEveryone(): array
    {
        $users = 0;
        $awarded = 0;

        User::query()->select('id')->chunkById(200, function ($chunk) use (&$users, &$awarded): void {
            foreach ($chunk as $user) {
                $users++;
                $awarded += self::refresh((int) $user->id)->count();
            }
        });

        return ['users' => $users, 'awarded' => $awarded];
    }

    /**
     * @return Collection<int, Badge>
     */
    private static function activeBadges(): Collection
    {
        return Badge::query()
            ->where('is_active', true)
            ->with(['missions' => fn ($query) => $query->where('is_active', true)->with('checkpoints')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, string>  $keys
     */
    private static function usesAny(Badge $badge, array $keys): bool
    {
        foreach ($badge->missions as $mission) {
            foreach ($mission->checkpoints as $checkpoint) {
                if (in_array($checkpoint->trigger_key, $keys, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $counts  mémo des activités déjà calculées pour ce membre
     * @return array{badge: Badge, earned: bool, awarded_at: mixed, percent: int, done: int, total: int, missions: array<int, array<string, mixed>>}
     */
    private static function describe(Badge $badge, int $userId, mixed $awardedAt, array &$counts): array
    {
        $earned = $awardedAt !== null;
        $missions = [];
        $total = 0;
        $done = 0;
        $ratioSum = 0.0;

        foreach ($badge->missions as $mission) {
            $checkpoints = [];

            foreach ($mission->checkpoints as $checkpoint) {
                $counts[$checkpoint->trigger_key] ??= ActivityCatalog::count($checkpoint->trigger_key, $userId);
                $target = max(1, (int) $checkpoint->target);
                $current = min($counts[$checkpoint->trigger_key], $target);
                $reached = $current >= $target;

                $total++;
                $done += $reached ? 1 : 0;
                $ratioSum += $current / $target;
                $checkpoints[] = [
                    'checkpoint' => $checkpoint,
                    'current' => $earned ? $target : $current,
                    'target' => $target,
                    'done' => $earned || $reached,
                ];
            }

            $missions[] = [
                'mission' => $mission,
                'checkpoints' => $checkpoints,
                'done' => $earned || ($checkpoints !== [] && collect($checkpoints)->every(fn (array $row): bool => $row['done'])),
            ];
        }

        return [
            'badge' => $badge,
            'earned' => $earned,
            'awarded_at' => $awardedAt,
            'percent' => $earned ? 100 : ($total === 0 ? 0 : (int) floor($ratioSum / $total * 100)),
            'done' => $total === 0 ? 0 : ($earned ? $total : $done),
            'total' => $total,
            'missions' => $missions,
        ];
    }

    private static function notify(int $userId, Badge $badge): void
    {
        AppNotification::query()->create([
            'user_id' => $userId,
            'type' => 'badge.awarded',
            'title' => 'Nouveau badge : '.$badge->name,
            'body' => 'Bravo, vous avez obtenu le badge '.$badge->tierLabel().' « '.$badge->name.' ».',
            'data' => ['badge_id' => $badge->id, 'tier' => $badge->tier],
        ]);
    }
}
