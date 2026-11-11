@php
    $groups = $activity->tasksByTeam();
    $all = $groups->flatten(1);
    $doneCount = $all->where('status', 'done')->count();
@endphp

<p class="text-sm text-muted">
    Liste des tâches de l’activité : elle part avec le compte rendu.
    @if($editable)
        Elle se gère depuis la fiche de l’activité (reprise de la liste type, ajout, affectation et suivi) et sera figée à la soumission.
    @endif
</p>

@if($all->isEmpty())
    <p class="text-sm text-muted">Aucune tâche n’a été définie pour cette activité.</p>
@else
    <p class="text-sm"><strong>{{ $doneCount }} / {{ $all->count() }}</strong> tâche(s) terminée(s)@if($all->contains(fn ($task) => $task->isLate())) · <span style="color:var(--danger)">{{ $all->filter(fn ($task) => $task->isLate())->count() }} en retard</span>@endif</p>

    @foreach($groups as $team => $tasks)
        <h3 style="margin-top:16px">{{ \App\Models\ActivityTask::TEAMS[$team] ?? $team }}</h3>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Tâche</th><th>Responsable</th><th>Échéance</th><th>Statut</th></tr></thead>
                <tbody>
                    @foreach($tasks as $task)
                        <tr>
                            <td class="text-sm">{{ $task->label }}@if($task->notes)<div class="cell-sub">{{ $task->notes }}</div>@endif</td>
                            <td class="text-sm">{{ $task->assignee?->name ?? '—' }}</td>
                            <td class="text-sm">{{ $task->due_at?->format('d/m/Y') ?? '—' }}</td>
                            <td><span class="badge badge-{{ ['todo' => 'neutral', 'doing' => 'warning', 'done' => 'success'][$task->status] ?? 'neutral' }}">{{ $task->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif

@if($editable)
    <p style="margin-top:12px"><a href="{{ route('admin.activities.show', $activity) }}#taches" class="btn btn-secondary btn-sm">Gérer les tâches</a></p>
@endif
