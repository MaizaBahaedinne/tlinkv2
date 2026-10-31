<?php

namespace App\Support\Challenges;

use App\Models\ActivityScore;
use App\Models\Challenge;
use App\Models\ClubActivity;
use App\Models\OrgNode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Notation des activités d'un défi et classement des clubs.
 *
 * Une activité est notée selon le barème qui correspond à sa nature (dans ou hors thématique). Sa note est la moyenne
 * des totaux des membres du comité qui ont noté tous les critères. Chaque club organisateur (porteur et co-organisateurs
 * ayant accepté) reçoit la note complète de l'activité.
 */
final class ChallengeScoring
{
    /**
     * @return array<int, array{total: ?float, max: int, evaluators: int}> indexé par identifiant d'activité
     */
    public static function activityTotals(Challenge $challenge): array
    {
        $criteria = $challenge->criteria()->get()->groupBy('kind');
        $activities = $challenge->activities()->where('status', 'published')->get(['id', 'in_theme']);
        $scores = ActivityScore::query()->whereIn('activity_id', $activities->pluck('id'))->get()->groupBy('activity_id');
        $totals = [];

        foreach ($activities as $activity) {
            $grid = $criteria->get($activity->in_theme ? 'theme' : 'off_theme', collect())->keyBy('id');
            $perEvaluator = ($scores->get($activity->id) ?? collect())
                ->filter(fn (ActivityScore $score): bool => $grid->has($score->criterion_id))
                ->groupBy('evaluator_id')
                ->filter(fn (Collection $rows): bool => $rows->count() === $grid->count())
                ->map(fn (Collection $rows): float => $rows->sum(fn (ActivityScore $score): float => min($score->points, (float) $grid[$score->criterion_id]->max_points)));

            $totals[$activity->id] = [
                'total' => $grid->isEmpty() || $perEvaluator->isEmpty() ? null : round($perEvaluator->avg(), 2),
                'max' => (int) $grid->sum('max_points'),
                'evaluators' => $perEvaluator->count(),
            ];
        }

        return $totals;
    }

    /**
     * Classement des clubs : points cumulés des activités notées, ex æquo au même rang.
     *
     * @return Collection<int, array{rank: int, club: OrgNode, points: float, theme_points: float, off_theme_points: float, activities: int}>
     */
    public static function ranking(Challenge $challenge): Collection
    {
        $totals = self::activityTotals($challenge);
        $activities = $challenge->activities()->where('status', 'published')->with('organizers')->get();
        $board = [];

        foreach ($activities as $activity) {
            $total = $totals[$activity->id]['total'] ?? null;

            if ($total === null) {
                continue;
            }

            foreach ($activity->organizerClubIds() as $clubId) {
                $board[$clubId] ??= ['points' => 0.0, 'theme_points' => 0.0, 'off_theme_points' => 0.0, 'activities' => 0];
                $board[$clubId]['points'] += $total;
                $board[$clubId][$activity->in_theme ? 'theme_points' : 'off_theme_points'] += $total;
                $board[$clubId]['activities']++;
            }
        }

        $clubs = OrgNode::query()->whereIn('id', array_keys($board))->get()->keyBy('id');
        $rows = collect($board)
            ->map(fn (array $row, int $clubId): array => $row + ['club' => $clubs->get($clubId)])
            ->filter(fn (array $row): bool => $row['club'] !== null)
            ->sort(fn (array $a, array $b): int => [$b['points'], $b['activities'], $a['club']->title] <=> [$a['points'], $a['activities'], $b['club']->title])
            ->values();

        $previous = null;
        $rank = 0;

        return $rows->map(function (array $row, int $index) use (&$previous, &$rank): array {
            if ($previous === null || round($row['points'], 2) !== round($previous, 2)) {
                $rank = $index + 1;
            }

            $previous = $row['points'];

            return ['rank' => $rank] + $row;
        });
    }

    /**
     * Enregistre les notes d'un membre du comité pour une activité (critère => points) ; la saisie est contrôlée contre le barème.
     *
     * @param  array<int|string, mixed>  $points
     * @param  array<int|string, mixed>  $comments
     */
    public static function record(Challenge $challenge, ClubActivity $activity, User $evaluator, array $points, array $comments = []): void
    {
        $grid = $challenge->criteriaOf($activity->in_theme ? 'theme' : 'off_theme')->keyBy('id');
        $errors = [];

        foreach ($grid as $id => $criterion) {
            $value = $points[$id] ?? null;

            if ($value === null || $value === '') {
                $errors["points.$id"] = 'Notez ce critère.';
            } elseif (! is_numeric($value) || (float) $value < 0 || (float) $value > $criterion->max_points) {
                $errors["points.$id"] = "La note doit être comprise entre 0 et {$criterion->max_points}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($grid as $id => $criterion) {
            ActivityScore::query()->updateOrCreate(
                ['activity_id' => $activity->id, 'criterion_id' => $id, 'evaluator_id' => $evaluator->id],
                ['points' => round((float) $points[$id], 2), 'comment' => filled($comments[$id] ?? null) ? mb_substr(trim((string) $comments[$id]), 0, 500) : null],
            );
        }
    }
}
