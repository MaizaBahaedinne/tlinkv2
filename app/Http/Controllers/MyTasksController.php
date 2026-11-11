<?php

namespace App\Http\Controllers;

use App\Support\Challenges\MyTasks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Mes tâches : les tâches d'activité de club confiées au membre connecté. */
class MyTasksController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Affectations non encore vues : mises en avant sur cette visite, puis marquées comme vues.
        $fresh = DB::table('activity_task_assignees')->where('user_id', $user->id)->whereNull('seen_at')->pluck('task_id')->map(fn ($id): int => (int) $id)->all();
        $tasks = MyTasks::for($user);
        MyTasks::markSeen($user);

        return view('tasks.mine', [
            'tasks' => $tasks,
            'columns' => collect(\App\Models\ActivityTask::STATUSES)->map(fn (string $label, string $status) => $tasks->where('status', $status)->values()),
            'fresh' => $fresh,
        ]);
    }
}
