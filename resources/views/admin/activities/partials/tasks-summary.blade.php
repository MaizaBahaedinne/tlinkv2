@php
    $all = $activity->tasks;
    $doneCount = $all->where('status', 'done')->count();
    $doingCount = $all->where('status', 'doing')->count();
    $percent = $all->isEmpty() ? 0 : (int) round($doneCount / $all->count() * 100);
    $late = $all->filter(fn ($task) => $task->isLate())->count();
@endphp

<article class="card" id="taches" style="margin-bottom:22px">
    <div class="card-head" style="flex-wrap:wrap;gap:10px">
        <div>
            <h2>Tâches de l’activité</h2>
            <div class="sub">Tableau façon Trello : cartes à glisser entre « À faire », « En cours » et « Terminée », chacune affectable à plusieurs membres. La liste part avec le compte rendu.</div>
        </div>
        <a href="{{ route('admin.activities.tasks.board', $activity) }}" class="btn btn-primary"><x-admin.icon name="activity" :size="16" /> {{ $tasksLocked ? 'Voir le tableau' : 'Ouvrir le tableau des tâches' }}</a>
    </div>
    <div class="card-body">
        @if($all->isEmpty())
            <p class="text-sm text-muted">Aucune tâche pour le moment : ouvrez le tableau pour reprendre la liste type de cette activité ou ajouter vos propres tâches.</p>
        @else
            <div style="display:flex;align-items:center;gap:12px">
                <div style="flex:1;height:8px;border-radius:999px;background:rgba(0,0,0,.08);overflow:hidden" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avancement des tâches"><div style="height:100%;width:{{ $percent }}%;background:var(--success)"></div></div>
                <span class="text-sm"><strong>{{ $doneCount }} / {{ $all->count() }}</strong> terminée(s) · {{ $doingCount }} en cours · {{ $percent }} %@if($late) · <span style="color:var(--danger)">{{ $late }} en retard</span>@endif</span>
            </div>
        @endif
        @if($tasksLocked)<p class="text-sm text-muted" style="margin-top:8px">Les tâches sont figées : elles sont parties avec le compte rendu.</p>@endif
    </div>
</article>
