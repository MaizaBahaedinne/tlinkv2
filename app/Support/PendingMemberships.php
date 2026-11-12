<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\User;

/** Demandes d'adhésion en attente que l'utilisateur a le droit de valider (colonne du fil d'actualité). */
final class PendingMemberships
{
    public const LIMIT = 5;

    /**
     * @return array{total: int, items: array<int, array<string, mixed>>, url: string}|null null si l'utilisateur ne valide pas d'adhésions
     */
    public static function for(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $responsibility = MembershipResponsibility::of($user, 'approval.review');

        if (! $responsibility->all() && ! AccessScope::can($user, 'approval.review')) {
            return null;
        }

        $service = app(ApprovalDecisionService::class);

        $rows = $responsibility->apply(Membership::query())
            ->where('status', 'pending')
            ->where('user_id', '!=', $user->id)
            ->with(['user:id,name', 'user.profile:id,user_id,photo_path', 'position:id,title'])
            ->oldest('created_at')
            ->get();

        $approvals = ApprovalRequest::query()
            ->where('approvable_type', Membership::class)->where('status', 'pending')
            ->whereIn('approvable_id', $rows->pluck('id'))->get()->keyBy('approvable_id');

        $decidable = $rows->filter(function (Membership $membership) use ($approvals, $service, $user): bool {
            if (! AccessScope::can($user, 'approval.review', $membership->scope)) {
                return false;
            }

            $approval = $approvals->get($membership->id);

            return $approval === null || $service->denialReason($approval, $user) === null;
        })->values();

        return [
            'total' => $decidable->count(),
            'url' => route('admin.approvals.index'),
            'items' => $decidable->take(self::LIMIT)->map(fn (Membership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user?->name ?? 'Membre',
                'photo_url' => $membership->user?->profile?->photoUrl(),
                'position' => $membership->position?->title ?? $membership->membership_type,
                'ago' => $membership->created_at?->locale('fr')->diffForHumans(),
            ])->all(),
        ];
    }
}
