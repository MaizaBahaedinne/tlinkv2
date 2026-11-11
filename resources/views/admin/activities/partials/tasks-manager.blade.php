@php
    $groups = $activity->tasksByTeam();
    $all = $groups->flatten(1);
    $doneCount = $all->where('status', 'done')->count();
    $percent = $all->isEmpty() ? 0 : (int) round($doneCount / $all->count() * 100);
    $late = $all->filter(fn ($task) => $task->isLate())->count();
    $line = 'border-top:1px solid rgba(0,0,0,.08);padding:10px 0';
@endphp

<article class="card" id="taches" style="margin-bottom:22px">
    <div class="card-head" style="flex-wrap:wrap;gap:10px">
        <div>
            <h2>Tâches de l’activité</h2>
            <div class="sub">
                Liste type « {{ $taskTemplate['label'] }} » : reprenez-la, adaptez-la, ajoutez les vôtres, affectez-les et suivez leur avancement. Elle part avec le compte rendu.
            </div>
        </div>
        @unless($tasksLocked)
            <form method="post" action="{{ route('admin.activities.tasks.template', $activity) }}">
                @csrf
                <button type="submit" class="btn btn-secondary"><x-admin.icon name="plus" :size="16" /> {{ $all->isEmpty() ? 'Reprendre la liste type' : 'Compléter avec la liste type' }}</button>
            </form>
        @endunless
    </div>

    <div class="card-body">
        @error('task')<p class="error" style="margin-bottom:10px">{{ $message }}</p>@enderror

        @if($tasksLocked)
            <p class="text-sm text-muted">Les tâches sont figées : elles sont parties avec le compte rendu.</p>
            @include('admin.activities.partials.report-taches', ['editable' => false])
        @else
            @if($all->isEmpty())
                <p class="text-sm text-muted">Aucune tâche pour le moment. Reprenez la liste type de ce type d’activité ({{ collect($taskTemplate['teams'])->flatten()->count() }} tâches réparties entre {{ count($taskTemplate['teams']) }} équipes) ou ajoutez vos propres tâches.</p>
            @else
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
                    <div style="flex:1;height:8px;border-radius:999px;background:rgba(0,0,0,.08);overflow:hidden" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avancement des tâches"><div style="height:100%;width:{{ $percent }}%;background:var(--success, #1a8f4c)"></div></div>
                    <span class="text-sm"><strong>{{ $doneCount }} / {{ $all->count() }}</strong> terminée(s) · {{ $percent }} %@if($late) · <span style="color:var(--danger)">{{ $late }} en retard</span>@endif</span>
                </div>
            @endif

            @foreach($groups as $team => $tasks)
                <h3 style="margin:18px 0 4px">{{ \App\Models\ActivityTask::TEAMS[$team] ?? $team }} <span class="text-muted text-sm">({{ $tasks->where('status', 'done')->count() }}/{{ $tasks->count() }})</span></h3>

                @foreach($tasks as $task)
                    <form method="post" action="{{ route('admin.activities.tasks.update', [$activity, $task]) }}" id="task-{{ $task->id }}" style="{{ $line }};display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start">
                        @csrf @method('PATCH')
                        <div class="field" style="flex:2 1 280px;margin:0"><input type="text" name="label" value="{{ $task->label }}" maxlength="255" required aria-label="Intitulé de la tâche"></div>
                        <div class="field" style="flex:0 1 130px;margin:0">
                            <select name="team" aria-label="Équipe">@foreach(\App\Models\ActivityTask::TEAMS as $value => $label)<option value="{{ $value }}" @selected($task->team === $value)>{{ $label }}</option>@endforeach</select>
                        </div>
                        <div class="field" style="flex:1 1 170px;margin:0">
                            <select name="assignee_id" aria-label="Responsable">
                                <option value="">Non affectée</option>
                                @foreach($assignees as $person)<option value="{{ $person->id }}" @selected($task->assignee_id === $person->id)>{{ $person->name }}@if($person->report_role) ({{ $person->report_role }})@endif</option>@endforeach
                                @if($task->assignee_id && ! $assignees->contains('id', $task->assignee_id))<option value="{{ $task->assignee_id }}" selected>{{ $task->assignee?->name }}</option>@endif
                            </select>
                        </div>
                        <div class="field" style="flex:0 1 150px;margin:0"><input type="date" name="due_at" value="{{ $task->due_at?->format('Y-m-d') }}" aria-label="Échéance" @if($task->isLate()) style="border-color:var(--danger)" title="Échéance dépassée" @endif></div>
                        <div class="field" style="flex:0 1 130px;margin:0">
                            <select name="status" aria-label="Statut">@foreach(\App\Models\ActivityTask::STATUSES as $value => $label)<option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>@endforeach</select>
                        </div>
                        <div style="display:flex;gap:6px">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Enregistrer la tâche">Enregistrer</button>
                            <button type="submit" form="task-del-{{ $task->id }}" class="btn btn-ghost btn-sm" title="Supprimer la tâche" aria-label="Supprimer la tâche"><x-admin.icon name="trash" :size="16" /></button>
                        </div>
                        <div class="field" style="flex:1 1 100%;margin:0"><input type="text" name="notes" value="{{ $task->notes }}" maxlength="1000" placeholder="Note de suivi (facultatif)" aria-label="Note de suivi"></div>
                    </form>
                    <form method="post" action="{{ route('admin.activities.tasks.destroy', [$activity, $task]) }}" id="task-del-{{ $task->id }}" data-confirm="« {{ \Illuminate\Support\Str::limit($task->label, 80) }} » sera supprimée de la liste." data-confirm-title="Supprimer cette tâche ?">
                        @csrf @method('DELETE')
                    </form>
                @endforeach
            @endforeach

            <h3 style="margin:22px 0 6px">Ajouter une tâche</h3>
            <form method="post" action="{{ route('admin.activities.tasks.store', $activity) }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start">
                @csrf
                <div class="field {{ $errors->has('label') ? 'has-error' : '' }}" style="flex:2 1 280px;margin:0">
                    <input type="text" name="label" value="{{ old('label') }}" maxlength="255" required placeholder="Intitulé de la tâche" aria-label="Intitulé de la nouvelle tâche">
                    @error('label')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field" style="flex:0 1 130px;margin:0">
                    <select name="team" aria-label="Équipe de la nouvelle tâche">@foreach(\App\Models\ActivityTask::TEAMS as $value => $label)<option value="{{ $value }}" @selected(old('team', 'event') === $value)>{{ $label }}</option>@endforeach</select>
                </div>
                <div class="field {{ $errors->has('assignee_id') ? 'has-error' : '' }}" style="flex:1 1 170px;margin:0">
                    <select name="assignee_id" aria-label="Responsable de la nouvelle tâche">
                        <option value="">Non affectée</option>
                        @foreach($assignees as $person)<option value="{{ $person->id }}" @selected((string) old('assignee_id') === (string) $person->id)>{{ $person->name }}@if($person->report_role) ({{ $person->report_role }})@endif</option>@endforeach
                    </select>
                    @error('assignee_id')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field" style="flex:0 1 150px;margin:0"><input type="date" name="due_at" value="{{ old('due_at') }}" aria-label="Échéance de la nouvelle tâche"></div>
                <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
            </form>
        @endif
    </div>
</article>
