<?php

namespace App\Support\Cards\Targets;

use App\Models\ActivityRegistration;
use App\Models\ClubActivity;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\Cards\CheckpointTarget;
use App\Support\Challenges\ActivityAccess;
use App\Support\OrgAccess;
use Illuminate\Database\Eloquent\Builder;

/** Activités des clubs : l'opérateur pointe celles des clubs qu'il gère (porteur ou co-organisateur accepté). */
final class ActivityTarget implements CheckpointTarget
{
    public function key(): string
    {
        return 'activity';
    }

    public function label(): string
    {
        return 'Activité de club';
    }

    public function options(User $operator): array
    {
        return $this->reachable($operator)
            ->where('status', 'published')
            ->whereBetween('starts_at', [now()->subDays(2), now()->addDays(2)])
            ->orderBy('starts_at')
            ->limit(60)
            ->get(['id', 'title', 'starts_at'])
            ->mapWithKeys(fn (ClubActivity $activity): array => [$activity->id => $activity->title.' — '.$activity->starts_at->format('d/m H:i')])
            ->all();
    }

    public function find(User $operator, int $id): ?array
    {
        $activity = $this->reachable($operator)->where('status', 'published')->find($id);

        if ($activity === null) {
            return null;
        }

        $club = OrgNode::query()->find($activity->club_id);

        return ['label' => $activity->title, 'scope' => $club ? OrgAccess::scopeOf($club) : null, 'node_id' => $activity->club_id];
    }

    public function note(int $id, User $member): ?string
    {
        $registered = ActivityRegistration::query()->where('activity_id', $id)->where('user_id', $member->id)->where('status', 'registered')->exists();

        return $registered ? 'Inscrit à l’activité' : 'Non inscrit à l’activité';
    }

    /** @return Builder<ClubActivity> */
    private function reachable(User $operator): Builder
    {
        $clubIds = ActivityAccess::manageableClubIds($operator);

        return ClubActivity::query()->when($clubIds !== null, fn (Builder $query) => $query->where(
            fn (Builder $inner) => $inner->whereIn('club_id', $clubIds)
                ->orWhereHas('organizers', fn (Builder $organizer) => $organizer->where('status', 'accepted')->whereIn('club_id', $clubIds))
        ));
    }
}
