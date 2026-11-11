<?php

namespace App\Support\Challenges;

use App\Models\ActivityTask;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** « Mes tâches » : tâches d'activité confiées à un membre, avec pastille des affectations non vues. */
class MyTasks
{
    /** @return array{total: int, unseen: int} */
    public static function counts(User $user): array
    {
        $row = DB::table('activity_task_assignees')->where('user_id', $user->id)
            ->selectRaw('count(*) as total, sum(case when seen_at is null then 1 else 0 end) as unseen')->first();

        return ['total' => (int) ($row->total ?? 0), 'unseen' => (int) ($row->unseen ?? 0)];
    }

    /** Ouvrir la liste « vaut lecture » : les affectations deviennent vues et les notifications d'affectation lues. */
    public static function markSeen(User $user): void
    {
        DB::table('activity_task_assignees')->where('user_id', $user->id)->whereNull('seen_at')->update(['seen_at' => now()]);

        AppNotification::query()->where('user_id', $user->id)->where('type', 'activity.task.assigned')->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ActivityTask> */
    public static function for(User $user)
    {
        return ActivityTask::query()
            ->whereHas('assignees', fn ($query) => $query->whereKey($user->id))
            ->with(['activity:id,title,type,club_id,starts_at,ends_at,challenge_id', 'activity.club:id,title', 'assignees:id,name'])
            ->orderByRaw("case status when 'doing' then 0 when 'todo' then 1 else 2 end")
            ->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id')
            ->get();
    }
}
