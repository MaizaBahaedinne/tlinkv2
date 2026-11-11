<?php

namespace App\Support\Cards\Targets;

use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\User;
use App\Support\Cards\CheckpointTarget;
use App\Support\MembershipResponsibility;
use Illuminate\Database\Eloquent\Builder;

/** Événements de la Fondation : l'opérateur pointe ceux de son périmètre (ou de son club). */
final class EventTarget implements CheckpointTarget
{
    public function key(): string
    {
        return 'event';
    }

    public function label(): string
    {
        return 'Événement';
    }

    public function options(User $operator): array
    {
        return $this->reachable($operator)
            ->whereBetween('starts_at', [now()->subDays(2), now()->addDays(2)])
            ->orderBy('starts_at')
            ->limit(60)
            ->get(['id', 'title', 'starts_at'])
            ->mapWithKeys(fn (FoundationEvent $event): array => [$event->id => $event->title.' — '.$event->starts_at->format('d/m H:i')])
            ->all();
    }

    public function find(User $operator, int $id): ?array
    {
        $event = $this->reachable($operator)->find($id);

        return $event === null ? null : ['label' => $event->title, 'scope' => $event->scope, 'node_id' => $event->node_id];
    }

    public function note(int $id, User $member): ?string
    {
        $registered = EventRegistration::query()->where('event_id', $id)->where('user_id', $member->id)
            ->whereIn('status', ['pending', 'confirmed', 'attended'])->exists();

        return $registered ? 'Inscrit à l’événement' : 'Non inscrit à l’événement';
    }

    /** @return Builder<FoundationEvent> */
    private function reachable(User $operator): Builder
    {
        return MembershipResponsibility::of($operator, 'event.manage')
            ->apply(FoundationEvent::query()->where('status', 'published'), 'scope', 'node_id');
    }
}
