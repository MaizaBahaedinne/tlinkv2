<?php

namespace App\Http\Controllers\Admin;

use App\Models\ActivityTask;
use App\Models\AppNotification;
use App\Models\ClubActivity;
use App\Models\User;
use App\Support\Challenges\ActivityAccess;
use App\Support\Challenges\ActivityTaskTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tâches d'une activité : le créateur reprend la liste type de son type d'activité, l'adapte, y ajoute les siennes,
 * les affecte à des membres et en suit l'avancement. La liste part avec le compte rendu ; elle est figée une fois celui-ci soumis.
 */
class ActivityTaskController extends AdminController
{
    /** Reprend la liste type du type d'activité : les tâches déjà présentes (même équipe, même intitulé) ne sont pas dupliquées. */
    public function importTemplate(Request $request, ClubActivity $activity): RedirectResponse
    {
        $this->authorizeTasks($request, $activity);

        $existing = $activity->tasks()->get(['team', 'label', 'sort_order']);
        $known = $existing->map(fn (ActivityTask $task): string => $this->key($task->team, $task->label))->all();
        $order = $existing->isEmpty() ? 0 : ((int) $existing->max('sort_order') + 1);
        $created = 0;

        foreach (ActivityTaskTemplates::tasksForType($activity->type) as $row) {
            if (in_array($this->key($row['team'], $row['label']), $known, true)) {
                continue;
            }

            $activity->tasks()->create([
                'team' => $row['team'],
                'label' => $row['label'],
                'from_template' => true,
                'sort_order' => $order + $row['sort_order'],
                'created_by' => $request->user()->id,
            ]);
            $created++;
        }

        $this->audit($request, 'admin.activity.tasks_imported', $activity, ['title' => $activity->title, 'created' => $created, 'set' => ActivityTaskTemplates::setKey($activity->type)]);

        return back()->with('status', $created > 0
            ? "{$created} tâche(s) reprise(s) de la liste « ".ActivityTaskTemplates::forType($activity->type)['label'].' ».'
            : 'La liste type est déjà reprise en totalité.');
    }

    public function store(Request $request, ClubActivity $activity): RedirectResponse
    {
        $this->authorizeTasks($request, $activity);

        $data = $this->validated($request, $activity);
        $order = (int) ($activity->tasks()->max('sort_order') ?? -1) + 1;

        $task = $activity->tasks()->create($this->attributes($data) + ['sort_order' => $order, 'created_by' => $request->user()->id]);

        $this->notifyAssignee($request, $activity, $task, null);
        $this->audit($request, 'admin.activity.task_added', $activity, ['title' => $activity->title, 'task' => $task->label]);

        return back()->with('status', 'Tâche ajoutée.');
    }

    public function update(Request $request, ClubActivity $activity, ActivityTask $task): RedirectResponse
    {
        $this->authorizeTasks($request, $activity);
        abort_unless($task->activity_id === $activity->id, 404);

        $before = $task->assignee_id;
        $data = $this->validated($request, $activity);
        $attributes = $this->attributes($data);
        $attributes['status'] = $data['status'] ?? $task->status;
        $attributes['completed_at'] = $attributes['status'] === 'done' ? ($task->completed_at ?? now()) : null;

        $task->update($attributes);

        $this->notifyAssignee($request, $activity, $task, $before);
        $this->audit($request, 'admin.activity.task_updated', $activity, ['title' => $activity->title, 'task' => $task->label, 'status' => $task->status]);

        return back()->with('status', 'Tâche mise à jour.');
    }

    public function destroy(Request $request, ClubActivity $activity, ActivityTask $task): RedirectResponse
    {
        $this->authorizeTasks($request, $activity);
        abort_unless($task->activity_id === $activity->id, 404);

        $label = $task->label;
        $task->delete();

        $this->audit($request, 'admin.activity.task_deleted', $activity, ['title' => $activity->title, 'task' => $label]);

        return back()->with('status', 'Tâche supprimée.');
    }

    /** Gestionnaire d'un des clubs organisateurs ; plus de modification une fois le compte rendu soumis. */
    private function authorizeTasks(Request $request, ClubActivity $activity): void
    {
        abort_unless(ActivityAccess::canManageActivity($request->user(), $activity), 403, 'Cette activité n’est pas celle de votre club.');

        $report = $activity->report;

        if ($report !== null && ! $report->isEditable()) {
            throw ValidationException::withMessages(['task' => 'Les tâches sont figées : elles sont parties avec le compte rendu.']);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ClubActivity $activity): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'team' => ['required', Rule::in(array_keys(ActivityTask::TEAMS))],
            'assignee_id' => ['nullable', 'integer'],
            'due_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(array_keys(ActivityTask::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'label.required' => 'Donnez un intitulé à la tâche.',
            'team.required' => 'Choisissez l’équipe de la tâche.',
        ]);

        if (filled($data['assignee_id'] ?? null) && ! ActivityAccess::reportCandidates($activity)->contains('id', (int) $data['assignee_id'])) {
            throw ValidationException::withMessages(['assignee_id' => 'Cette personne ne fait pas partie des clubs organisateurs.']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'label' => trim($data['label']),
            'team' => $data['team'],
            'assignee_id' => filled($data['assignee_id'] ?? null) ? (int) $data['assignee_id'] : null,
            'due_at' => $data['due_at'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ];
    }

    /** Prévient la personne à qui la tâche vient d'être confiée (sauf si c'est elle qui l'a fait). */
    private function notifyAssignee(Request $request, ClubActivity $activity, ActivityTask $task, ?int $previous): void
    {
        $assigneeId = $task->assignee_id;

        if ($assigneeId === null || $assigneeId === $previous || $assigneeId === $request->user()->id) {
            return;
        }

        $assignee = User::query()->find($assigneeId);

        if ($assignee === null) {
            return;
        }

        $url = ActivityAccess::canManageActivity($assignee, $activity)
            ? route('admin.activities.show', $activity, false)
            : route('activities.show', $activity, false);

        AppNotification::query()->create([
            'user_id' => $assigneeId,
            'type' => 'activity.task.assigned',
            'title' => 'Une tâche vous est confiée',
            'body' => "« {$task->label} » — activité « {$activity->title} »".($task->due_at ? ', à rendre pour le '.$task->due_at->format('d/m/Y') : '').'.',
            'data' => ['activity_id' => $activity->id, 'task_id' => $task->id, 'url' => $url],
        ]);
    }

    private function key(string $team, string $label): string
    {
        return $team.'|'.mb_strtolower(trim($label));
    }
}
