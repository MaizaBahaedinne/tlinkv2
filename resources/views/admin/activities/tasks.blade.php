@extends('admin.layouts.app')

@section('title', 'Tâches · '.$activity->title)
@section('section', 'Défis')

@php
    $all = $activity->tasks;
    $doneCount = $all->where('status', 'done')->count();
    $percent = $all->isEmpty() ? 0 : (int) round($doneCount / $all->count() * 100);
    $teamColors = ['event' => ['#fde8ec', '#a30027'], 'marketing' => ['#efe9fd', '#6d3fd0'], 'hr' => ['#e2f5ec', '#0f7a58'], 'sponsoring' => ['#fdf0d6', '#a85a08']];
    $me = auth()->id();
@endphp

@push('head')
<style>
    .tb-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 16px; }
    .tb-progress { flex: 1 1 260px; display: flex; align-items: center; gap: 12px; }
    .tb-progress .track { flex: 1; height: 8px; border-radius: 999px; background: rgba(0, 0, 0, .08); overflow: hidden; }
    .tb-progress .fill { height: 100%; background: var(--success); transition: width .25s; }
    .tb-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .tb-filters select { min-width: 150px; }
    .tb-board { display: grid; grid-template-columns: repeat(3, minmax(260px, 1fr)); gap: 16px; align-items: start; overflow-x: auto; padding-bottom: 8px; }
    .tb-col { background: #f1ebec; border-radius: 14px; padding: 10px; display: flex; flex-direction: column; gap: 10px; min-height: 140px; }
    .tb-col.is-over { outline: 2px dashed var(--cyan); background: var(--cyan-soft); }
    .tb-col-head { display: flex; align-items: center; justify-content: space-between; padding: 4px 6px; font-weight: 700; font-family: var(--font-display); }
    .tb-col-head .n { background: var(--surface); border-radius: 999px; padding: 0 9px; font-size: 12px; color: var(--muted); }
    .tb-cards { display: flex; flex-direction: column; gap: 8px; min-height: 24px; }
    .tb-card { background: var(--surface); border-radius: 10px; padding: 10px 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--line); display: grid; gap: 8px; text-align: left; width: 100%; font: inherit; color: inherit; }
    .tb-card[draggable="true"] { cursor: grab; }
    .tb-card.is-clickable { cursor: pointer; }
    .tb-card:hover { border-color: var(--line-strong); box-shadow: var(--shadow); }
    .tb-card.is-dragging { opacity: .45; }
    .tb-card[hidden] { display: none; }
    .tb-team { display: inline-block; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 999px; }
    .tb-label { font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
    .tb-card.is-done .tb-label { text-decoration: line-through; color: var(--muted); }
    .tb-notes { font-size: 12.5px; color: var(--muted); overflow-wrap: anywhere; }
    .tb-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .tb-due { font-size: 12px; font-weight: 600; color: var(--muted); }
    .tb-due.is-late { color: var(--danger); }
    .tb-people { display: flex; }
    .tb-av { width: 26px; height: 26px; border-radius: 50%; background: var(--navy-800); color: #fff; font-size: 10.5px; font-weight: 700; display: grid; place-items: center; border: 2px solid var(--surface); margin-left: -6px; }
    .tb-people .tb-av:first-child { margin-left: 0; }
    .tb-av.more { background: var(--neutral); }
    .tb-add { border: 0; background: transparent; color: var(--muted); font: inherit; font-weight: 600; padding: 8px; border-radius: 8px; cursor: pointer; text-align: left; }
    .tb-add:hover { background: rgba(0, 0, 0, .06); color: var(--text); }
    .tb-people-pick { display: grid; gap: 4px; max-height: 190px; overflow-y: auto; border: 1px solid var(--line-strong); border-radius: 10px; padding: 8px 10px; }
    .tb-people-pick label { display: flex; align-items: center; gap: 8px; font-size: 13.5px; }
    .tb-form { display: grid; gap: 14px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .tb-form .wide { grid-column: 1 / -1; }
    @media (max-width: 720px) { .tb-form { grid-template-columns: 1fr; } .tb-board { grid-template-columns: repeat(3, 82vw); } }
</style>
@endpush

@section('content')
    <x-admin.page-header :title="'Tâches · '.$activity->title" :subtitle="($activity->challenge?->title ?? 'Activité libre').' · '.$activity->typeLabel().' · '.$activity->starts_at->format('d/m/Y H:i')">
        <x-slot:actions>
            <a href="{{ route('admin.activities.show', $activity) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à l’activité</a>
            @unless($locked)
                <form method="post" action="{{ route('admin.activities.tasks.template', $activity) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> {{ $all->isEmpty() ? 'Reprendre la liste type' : 'Compléter avec la liste type' }}</button>
                </form>
            @endunless
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="card" style="margin-bottom:16px"><div class="card-body">
            @foreach($errors->all() as $message)<p class="error">{{ $message }}</p>@endforeach
        </div></div>
    @endif

    @if($locked)
        <p class="text-sm text-muted" style="margin-bottom:12px">Les tâches sont figées : elles sont parties avec le compte rendu.</p>
    @elseif($all->isEmpty())
        <p class="text-sm text-muted" style="margin-bottom:12px">Aucune tâche pour le moment. Reprenez la liste type « {{ $template['label'] }} » ({{ collect($template['teams'])->flatten(1)->count() }} tâches réparties entre {{ count($template['teams']) }} équipes) ou ajoutez vos propres cartes. Glissez ensuite chaque carte d’une colonne à l’autre pour suivre l’avancement.</p>
    @endif

    <div class="tb-bar">
        <div class="tb-progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avancement des tâches">
            <div class="track"><div class="fill" id="tb-fill" style="width:{{ $percent }}%"></div></div>
            <span class="text-sm"><strong id="tb-done">{{ $doneCount }}</strong> / <span id="tb-total">{{ $all->count() }}</span> terminée(s)</span>
        </div>
        <div class="tb-filters">
            <select id="tb-team" aria-label="Filtrer par équipe">
                <option value="">Toutes les équipes</option>
                @foreach(\App\Models\ActivityTask::TEAMS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
            <label class="text-sm" style="display:flex;align-items:center;gap:6px"><input type="checkbox" id="tb-mine"> Mes tâches</label>
        </div>
    </div>

    <div class="tb-board" id="tb-board" data-move-url="{{ route('admin.activities.tasks.move', [$activity, '__ID__']) }}" data-editable="{{ $locked ? '0' : '1' }}">
        @foreach(\App\Models\ActivityTask::STATUSES as $status => $statusLabel)
            <section class="tb-col" data-status="{{ $status }}" aria-label="{{ $statusLabel }}">
                <div class="tb-col-head"><span>{{ $statusLabel }}</span><span class="n" data-count>{{ $columns[$status]->count() }}</span></div>
                <div class="tb-cards">
                    @foreach($columns[$status] as $task)
                        @php
                            [$bg, $fg] = $teamColors[$task->team] ?? ['#f4eff0', '#675458'];
                            $payload = [
                                'id' => $task->id, 'label' => $task->label, 'team' => $task->team, 'status' => $task->status,
                                'due_at' => $task->due_at?->format('Y-m-d'), 'notes' => $task->notes,
                                'assignees' => $task->assignees->pluck('id')->all(),
                                'update' => route('admin.activities.tasks.update', [$activity, $task]),
                                'destroy' => route('admin.activities.tasks.destroy', [$activity, $task]),
                            ];
                        @endphp
                        <div class="tb-card {{ $task->isDone() ? 'is-done' : '' }} {{ $locked ? '' : 'is-clickable' }}" data-id="{{ $task->id }}" data-team="{{ $task->team }}" data-people="{{ $task->assignees->pluck('id')->implode(',') }}"
                             @unless($locked) draggable="true" tabindex="0" role="button" data-task="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}" aria-label="Modifier la tâche : {{ $task->label }}" @endunless>
                            <div><span class="tb-team" style="background:{{ $bg }};color:{{ $fg }}">{{ $task->teamLabel() }}</span></div>
                            <div class="tb-label">{{ $task->label }}</div>
                            @if($task->notes)<div class="tb-notes">{{ \Illuminate\Support\Str::limit($task->notes, 120) }}</div>@endif
                            <div class="tb-foot">
                                <span class="tb-due {{ $task->isLate() ? 'is-late' : '' }}">@if($task->due_at){{ $task->isLate() ? 'En retard · ' : '' }}{{ $task->due_at->format('d/m/Y') }}@endif</span>
                                @if($task->assignees->isNotEmpty())
                                    <span class="tb-people" title="{{ $task->assignees->pluck('name')->implode(', ') }}">
                                        @foreach($task->assignees->take(4) as $person)
                                            <span class="tb-av">{{ mb_strtoupper(collect(preg_split('/\s+/', trim($person->name)))->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->implode('')) }}</span>
                                        @endforeach
                                        @if($task->assignees->count() > 4)<span class="tb-av more">+{{ $task->assignees->count() - 4 }}</span>@endif
                                    </span>
                                @else
                                    <span class="text-sm text-muted">Non affectée</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @unless($locked)
                    <button type="button" class="tb-add" data-add="{{ $status }}">+ Ajouter une carte</button>
                @endunless
            </section>
        @endforeach
    </div>

    @unless($locked)
        <dialog id="tb-dialog" class="modal" aria-labelledby="tb-dialog-title">
            <div class="modal-head">
                <h2 id="tb-dialog-title">Tâche</h2>
                <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Fermer">✕</button>
            </div>
            <form method="post" action="{{ route('admin.activities.tasks.store', $activity) }}" id="tb-form" class="modal-form" data-store="{{ route('admin.activities.tasks.store', $activity) }}">
                @csrf
                <input type="hidden" name="_method" value="PATCH" id="tb-method" disabled>
                <div class="modal-body" style="display:block">
                    <div class="tb-form">
                        <div class="field wide"><label for="tb-f-label">Intitulé</label><input type="text" name="label" id="tb-f-label" maxlength="255" required></div>
                        <div class="field"><label for="tb-f-team">Équipe</label>
                            <select name="team" id="tb-f-team">@foreach(\App\Models\ActivityTask::TEAMS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        </div>
                        <div class="field"><label for="tb-f-status">Colonne</label>
                            <select name="status" id="tb-f-status">@foreach(\App\Models\ActivityTask::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                        </div>
                        <div class="field"><label for="tb-f-due">Échéance</label><input type="date" name="due_at" id="tb-f-due"></div>
                        <div class="field wide">
                            <label>Responsables <span class="text-muted text-sm">(un ou plusieurs)</span></label>
                            <div class="tb-people-pick">
                                @forelse($people as $person)
                                    <label><input type="checkbox" name="assignee_ids[]" value="{{ $person->id }}"> {{ $person->name }}@if($person->report_role) <span class="text-muted text-sm">({{ $person->report_role }})</span>@endif</label>
                                @empty
                                    <span class="text-sm text-muted">Aucun membre disponible.</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="field wide"><label for="tb-f-notes">Note de suivi</label><textarea name="notes" id="tb-f-notes" rows="3" maxlength="1000"></textarea></div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="submit" form="tb-delete" class="btn btn-ghost" id="tb-delete-btn" style="margin-right:auto;color:var(--danger)"><x-admin.icon name="trash" :size="16" /> Supprimer</button>
                    <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </dialog>
        <form method="post" id="tb-delete" data-confirm="Cette tâche sera supprimée de la liste." data-confirm-title="Supprimer cette tâche ?">@csrf @method('DELETE')</form>
    @endunless
@endsection

@push('scripts')
<script>
(function () {
    var board = document.getElementById('tb-board');
    if (!board) { return; }

    var me = '{{ $me }}';
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var editable = board.getAttribute('data-editable') === '1';
    var team = document.getElementById('tb-team');
    var mine = document.getElementById('tb-mine');

    function counts() {
        var total = 0;
        board.querySelectorAll('.tb-col').forEach(function (col) {
            var n = col.querySelectorAll('.tb-card').length;
            col.querySelector('[data-count]').textContent = n;
            total += n;
        });
        var done = board.querySelectorAll('.tb-col[data-status="done"] .tb-card').length;
        document.getElementById('tb-done').textContent = done;
        document.getElementById('tb-total').textContent = total;
        document.getElementById('tb-fill').style.width = (total ? Math.round(done / total * 100) : 0) + '%';
    }

    function filter() {
        board.querySelectorAll('.tb-card').forEach(function (card) {
            var okTeam = !team.value || card.getAttribute('data-team') === team.value;
            var okMine = !mine.checked || (',' + card.getAttribute('data-people') + ',').indexOf(',' + me + ',') !== -1;
            card.hidden = !(okTeam && okMine);
        });
    }
    team.addEventListener('change', filter);
    mine.addEventListener('change', filter);

    if (!editable) { return; }

    // Glisser-déposer d'une carte vers une autre colonne : seul le statut change.
    var dragged = null;
    board.addEventListener('dragstart', function (event) {
        var card = event.target.closest('.tb-card');
        if (!card) { return; }
        dragged = card;
        card.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.getAttribute('data-id'));
    });
    board.addEventListener('dragend', function () {
        if (dragged) { dragged.classList.remove('is-dragging'); }
        dragged = null;
        board.querySelectorAll('.tb-col').forEach(function (col) { col.classList.remove('is-over'); });
    });
    board.querySelectorAll('.tb-col').forEach(function (col) {
        col.addEventListener('dragover', function (event) { if (dragged) { event.preventDefault(); col.classList.add('is-over'); } });
        col.addEventListener('dragleave', function (event) { if (!col.contains(event.relatedTarget)) { col.classList.remove('is-over'); } });
        col.addEventListener('drop', function (event) {
            event.preventDefault();
            col.classList.remove('is-over');
            if (!dragged) { return; }
            var card = dragged;
            var status = col.getAttribute('data-status');
            var origin = card.parentElement;
            if (origin === col.querySelector('.tb-cards')) { return; }
            col.querySelector('.tb-cards').appendChild(card);
            card.classList.toggle('is-done', status === 'done');
            counts();
            fetch(board.getAttribute('data-move-url').replace('__ID__', card.getAttribute('data-id')), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ status: status })
            }).then(function (response) {
                if (!response.ok) { throw new Error('move'); }
                var data = JSON.parse(card.getAttribute('data-task'));
                data.status = status;
                card.setAttribute('data-task', JSON.stringify(data));
            }).catch(function () { window.location.reload(); });
        });
    });

    // Fenêtre d'ajout / de modification d'une carte.
    var dialog = document.getElementById('tb-dialog');
    var form = document.getElementById('tb-form');
    var method = document.getElementById('tb-method');
    var del = document.getElementById('tb-delete');
    var delBtn = document.getElementById('tb-delete-btn');

    function open(task, status) {
        task = task || { label: '', team: 'event', status: status || 'todo', due_at: '', notes: '', assignees: [] };
        document.getElementById('tb-dialog-title').textContent = task.id ? 'Modifier la tâche' : 'Nouvelle tâche';
        form.setAttribute('action', task.id ? task.update : form.getAttribute('data-store'));
        method.disabled = !task.id;
        delBtn.style.display = task.id ? '' : 'none';
        if (task.id) { del.setAttribute('action', task.destroy); }
        document.getElementById('tb-f-label').value = task.label;
        document.getElementById('tb-f-team').value = task.team;
        document.getElementById('tb-f-status').value = task.status;
        document.getElementById('tb-f-due').value = task.due_at || '';
        document.getElementById('tb-f-notes').value = task.notes || '';
        form.querySelectorAll('input[name="assignee_ids[]"]').forEach(function (box) {
            box.checked = task.assignees.indexOf(parseInt(box.value, 10)) !== -1;
        });
        dialog.showModal();
        document.getElementById('tb-f-label').focus();
    }

    board.addEventListener('click', function (event) {
        var add = event.target.closest('[data-add]');
        if (add) { open(null, add.getAttribute('data-add')); return; }
        var card = event.target.closest('.tb-card[data-task]');
        if (card) { open(JSON.parse(card.getAttribute('data-task'))); }
    });
    board.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') { return; }
        var card = event.target.closest('.tb-card[data-task]');
        if (card) { event.preventDefault(); open(JSON.parse(card.getAttribute('data-task'))); }
    });
    dialog.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () { dialog.close(); });
    });
})();
</script>
@endpush
