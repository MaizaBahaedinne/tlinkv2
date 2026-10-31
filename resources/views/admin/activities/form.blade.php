@extends('admin.layouts.app')

@php($editing = $activity->exists)
@php($dt = fn ($value) => $value?->format('Y-m-d\TH:i'))

@section('title', $editing ? 'Modifier l’activité' : 'Nouvelle activité')
@section('section', 'Défis')

@section('content')
    <x-admin.page-header :title="$editing ? 'Modifier « '.$activity->title.' »' : 'Nouvelle activité'" subtitle="Une activité s’inscrit dans un défi : dans la thématique ou hors thématique (barème différent).">
        <x-slot:actions>
            <a href="{{ $editing ? route('admin.activities.show', $activity) : route('admin.activities.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $editing ? route('admin.activities.update', $activity) : route('admin.activities.store') }}" class="card card-flush">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card-body">
            <div class="form-grid">
                @if($editing)
                    <div class="field"><label>Défi</label><p>{{ $activity->challenge->title }}</p></div>
                    <div class="field"><label>Club porteur</label><p>{{ $activity->club->title }}</p></div>
                @else
                    <x-admin.select name="challenge_id" label="Défi" :options="$challenges->pluck('title', 'id')->all()" :value="$activity->challenge_id" required hint="Seuls les défis ouverts aux activités et auxquels votre club est invité apparaissent." />
                    <x-admin.select name="club_id" label="Club porteur" :options="$clubs->pluck('title', 'id')->all()" :value="$activity->club_id" required />
                @endif
                <x-admin.input name="title" label="Titre" :value="$activity->title" required :maxlength="255" :span="true" />
                <x-admin.textarea name="description" label="Description" :value="$activity->description" :maxlength="5000" />
                <x-admin.select name="type" label="Type d’activité" :options="\App\Models\ClubActivity::TYPES" :value="$activity->type" required :placeholder="null" />
                <div class="field {{ $errors->has('in_theme') ? 'has-error' : '' }}">
                    <label>Nature</label>
                    <label class="check"><input type="radio" name="in_theme" value="1" @checked((string) old('in_theme', $activity->in_theme ? 1 : 0) === '1')> Dans la thématique du défi</label>
                    <label class="check"><input type="radio" name="in_theme" value="0" @checked((string) old('in_theme', $activity->in_theme ? 1 : 0) === '0')> Hors thématique (barème différent)</label>
                    @error('in_theme')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-admin.select name="audience" label="Public" :options="\App\Models\ClubActivity::AUDIENCES" :value="$activity->audience" required :placeholder="null" />
                <x-admin.select name="department_id" label="Département concerné" :options="[]" :value="$activity->department_id" placeholder="Choisir un département" hint="Uniquement pour le public « Département du club »." />
                <x-admin.input name="starts_at" type="datetime-local" label="Début" :value="$dt($activity->starts_at)" required hint="Pendant la période du défi." />
                <x-admin.input name="ends_at" type="datetime-local" label="Fin" :value="$dt($activity->ends_at)" />
                <x-admin.input name="location" label="Lieu" :value="$activity->location" :maxlength="255" />
                <x-admin.input name="capacity" type="number" label="Places" :value="$activity->capacity" min="1" hint="Vide : illimité. Au-delà : liste d’attente." />
                <x-admin.input name="registration_ends_at" type="datetime-local" label="Clôture des inscriptions" :value="$dt($activity->registration_ends_at)" hint="Vide : jusqu’à la fin de l’activité." />
                <x-admin.select name="status" label="Statut" :options="\App\Models\ClubActivity::STATUSES" :value="$activity->status" required :placeholder="null" hint="Publiée : visible des membres et soumise à la notation du comité." />
            </div>

            <h3>Formulaire d’inscription</h3>
            <p class="text-sm text-muted">Questions posées aux participants lors de l’inscription (nom et e-mail sont déjà connus). Une ligne sans intitulé est ignorée. Pour un choix, listez les options séparées par des virgules.</p>
            @php($fieldRows = array_pad($fieldRows, max(3, count($fieldRows) + 1), ['label' => '', 'type' => 'text', 'required' => false, 'options' => '']))
            <div class="field {{ $errors->has('fields') ? 'has-error' : '' }}">
                <div id="field-rows">
                    @foreach($fieldRows as $i => $row)
                        <div class="form-grid" style="margin-top:8px" data-row>
                            <div class="field"><input type="text" name="fields[{{ $i }}][label]" maxlength="160" placeholder="Question (ex. Taille de t-shirt)" aria-label="Question" value="{{ $row['label'] }}"></div>
                            <div class="field">
                                <select name="fields[{{ $i }}][type]" aria-label="Type de réponse">
                                    @foreach(\App\Models\ClubActivity::FIELD_TYPES as $value => $label)<option value="{{ $value }}" @selected($row['type'] === $value)>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="field"><input type="text" name="fields[{{ $i }}][options]" maxlength="400" placeholder="Options : S, M, L, XL" aria-label="Options du choix" value="{{ $row['options'] }}"></div>
                            <div class="field"><label class="check"><input type="checkbox" name="fields[{{ $i }}][required]" value="1" @checked($row['required'])> Obligatoire</label></div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-secondary btn-sm" id="add-field" style="margin-top:8px">+ Ajouter une question</button>
                @error('fields')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ $editing ? route('admin.activities.show', $activity) : route('admin.activities.index') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $editing ? 'Enregistrer' : 'Créer l’activité' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var departments = @json($departments);
        var current = @json((string) old('department_id', $activity->department_id));
        var clubId = @json($editing ? (string) $activity->club_id : null);
        var club = document.getElementById('f-club_id');
        var audience = document.getElementById('f-audience');
        var select = document.getElementById('f-department_id');

        function refresh() {
            var id = club ? club.value : clubId;
            var list = departments[id] || {};
            select.innerHTML = '<option value="">Choisir un département</option>';
            Object.keys(list).forEach(function (key) {
                var option = document.createElement('option');
                option.value = key; option.textContent = list[key];
                if (key === current) option.selected = true;
                select.appendChild(option);
            });
            select.closest('.field').hidden = audience.value !== 'department';
        }

        if (club) club.addEventListener('change', function () { current = ''; refresh(); });
        audience.addEventListener('change', refresh);
        refresh();

        document.getElementById('add-field').addEventListener('click', function () {
            var box = document.getElementById('field-rows');
            var rows = box.querySelectorAll('[data-row]');
            var clone = rows[rows.length - 1].cloneNode(true);
            clone.querySelectorAll('input,select').forEach(function (input) {
                if (input.type === 'checkbox') input.checked = false; else if (input.tagName !== 'SELECT') input.value = '';
                input.name = input.name.replace(/fields\[\d+\]/, 'fields[' + rows.length + ']');
            });
            box.appendChild(clone);
        });
    })();
</script>
@endpush
