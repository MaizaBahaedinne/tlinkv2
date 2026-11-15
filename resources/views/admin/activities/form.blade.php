@extends('admin.layouts.app')

@php
    $editing = $activity->exists;
    $dt = fn ($value) => $value?->format('Y-m-d\TH:i');

    // Étape portant la première erreur de validation (après un envoi refusé, on rouvre la bonne étape).
    $stepOf = function (string $key): int {
        $root = explode('.', $key)[0];

        return match (true) {
            in_array($root, ['audience', 'department_id', 'starts_at', 'ends_at', 'location', 'capacity', 'registration_ends_at', 'status'], true) => 2,
            str_starts_with($root, 'committee') => 3,
            $root === 'fields' => 4,
            default => 1,
        };
    };
    $startStep = $errors->any() ? $stepOf((string) ($errors->keys()[0] ?? '')) : 1;
    $steps = [1 => 'Général', 2 => 'Date et lieu', 3 => 'Comité d’organisation', 4 => 'Inscription des participants'];
@endphp

@section('title', $editing ? 'Modifier l’activité' : 'Nouvelle activité')
@section('section', 'Défis')

@section('content')
    <x-admin.page-header :title="$editing ? 'Modifier « '.$activity->title.' »' : 'Nouvelle activité'" subtitle="Une activité peut s’inscrire dans un défi (dans la thématique ou hors thématique, barèmes différents) ou rester libre : le défi est un choix, pas une obligation.">
        <x-slot:actions>
            <a href="{{ $editing ? route('admin.activities.show', $activity) : route('admin.activities.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $editing ? route('admin.activities.update', $activity) : route('admin.activities.store') }}" class="card card-flush" enctype="multipart/form-data" id="wizard" novalidate data-editing="{{ $editing ? '1' : '0' }}" data-start="{{ $startStep }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <ol class="stepper is-4 wizard-nav" aria-label="Étapes du formulaire">
            @foreach($steps as $number => $label)
                <li data-nav="{{ $number }}"><button type="button" class="step-btn" data-go="{{ $number }}">{{ $label }}</button></li>
            @endforeach
        </ol>

        <div class="card-body">
            {{-- Étape 1 : général --}}
            <section data-step="1" aria-labelledby="step-1-title">
                <h3 id="step-1-title" class="wizard-title">1. Général</h3>
                <div class="form-grid">
                    @if($editing)
                        <div class="field"><label>Défi</label><p>{{ $activity->challenge?->title ?? 'Aucun (activité libre)' }}</p></div>
                        <div class="field"><label>Club porteur</label><p>{{ $activity->club->title }}</p></div>
                    @else
                        <x-admin.select name="challenge_id" label="Défi (facultatif)" :options="$challenges->pluck('title', 'id')->all()" :value="$activity->challenge_id" placeholder="Aucun défi (activité libre)" hint="Laissez vide pour une activité libre : sans thématique, sans barème ni comité. Seuls les défis ouverts et auxquels votre club est invité apparaissent." />
                        <x-admin.select name="club_id" label="Club porteur" :options="$clubs->pluck('title', 'id')->all()" :value="$activity->club_id" required />
                    @endif
                    <x-admin.input name="title" label="Titre" :value="$activity->title" required :maxlength="255" :span="true" />
                    <x-admin.textarea name="description" label="Description" :value="$activity->description" :maxlength="5000" />
                    <div class="field span-2 {{ $errors->has('cover') ? 'has-error' : '' }}">
                        <label for="f-cover">Image de couverture</label>
                        <input id="f-cover" type="file" name="cover" accept="image/png,image/jpeg,image/webp">
                        <p class="hint">Bandeau en haut de la fiche (format paysage conseillé, 4 Mo max). Sans image, un fond aux couleurs de T.Link est utilisé. Le logo du club s’affiche automatiquement.</p>
                        @if($coverUrl = $activity->coverUrl())
                            <img src="{{ $coverUrl }}" alt="Couverture actuelle" style="margin-top:8px;max-width:100%;height:140px;object-fit:cover;border-radius:10px">
                            <label class="check" style="margin-top:8px"><input type="checkbox" name="cover_remove" value="1"> Supprimer cette image</label>
                        @endif
                        @error('cover')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <x-admin.select name="type" label="Type d’activité" :options="\App\Models\ClubActivity::TYPES" :value="$activity->type" required :placeholder="null" />
                    @if(! $editing || $activity->hasChallenge())
                        <div class="field {{ $errors->has('in_theme') ? 'has-error' : '' }}" id="nature-field" @if(! $editing && ! old('challenge_id', $activity->challenge_id)) hidden @endif>
                            <label>Nature</label>
                            <label class="check"><input type="radio" name="in_theme" value="1" @checked((string) old('in_theme', $activity->in_theme ? 1 : 0) === '1')> Dans la thématique du défi</label>
                            <label class="check"><input type="radio" name="in_theme" value="0" @checked((string) old('in_theme', $activity->in_theme ? 1 : 0) === '0')> Hors thématique (barème différent)</label>
                            @error('in_theme')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endif
                </div>
            </section>

            {{-- Étape 2 : date, lieu, public --}}
            <section data-step="2" hidden aria-labelledby="step-2-title">
                <h3 id="step-2-title" class="wizard-title">2. Date, lieu et public</h3>
                <div class="form-grid">
                    <x-admin.select name="audience" label="Public" :options="\App\Models\ClubActivity::AUDIENCES" :value="$activity->audience" required :placeholder="null" />
                    <x-admin.select name="department_id" label="Département concerné" :options="[]" :value="$activity->department_id" placeholder="Choisir un département" hint="Uniquement pour le public « Département du club »." />
                    <x-admin.input name="starts_at" type="datetime-local" label="Début" :value="$dt($activity->starts_at)" required hint="Pendant la période du défi, s’il y en a un." />
                    <x-admin.input name="ends_at" type="datetime-local" label="Fin" :value="$dt($activity->ends_at)" />
                    <x-admin.input name="location" label="Lieu" :value="$activity->location" :maxlength="255" />
                    <x-admin.input name="capacity" type="number" label="Places" :value="$activity->capacity" min="1" hint="Vide : illimité. Au-delà : liste d’attente." />
                    <x-admin.input name="registration_ends_at" type="datetime-local" label="Clôture des inscriptions" :value="$dt($activity->registration_ends_at)" hint="Vide : jusqu’à la fin de l’activité." />
                    <x-admin.select name="status" label="Statut" :options="\App\Models\ClubActivity::STATUSES" :value="$activity->status" required :placeholder="null" hint="Publiée : visible des membres (et notée par le comité si l’activité est dans un défi)." />
                </div>
            </section>

            {{-- Étape 3 : comité d'organisation --}}
            <section data-step="3" hidden aria-labelledby="step-3-title">
                <h3 id="step-3-title" class="wizard-title">3. Comité d’organisation</h3>
                <p class="text-sm text-muted">Sélectionnez les membres du club (adhérents validés et titulaires de postes) qui organisent l’activité, et donnez à chacun son rôle. Ils sont prévenus et retrouveront leurs tâches dans « Mes tâches ». Cette étape est facultative : vous pourrez compléter le comité plus tard.</p>
                <div class="field {{ $errors->has('committee_ids') || $errors->has('committee_roles.*') ? 'has-error' : '' }}" id="committee"
                     data-url="{{ route('admin.activities.committee') }}">
                    <p class="hint" id="committee-hint"></p>
                    <input type="search" id="committee-search" placeholder="Rechercher une personne du club" aria-label="Rechercher une personne du comité" maxlength="80" hidden>
                    <div class="committee-list" id="committee-list"></div>
                    @error('committee_ids')<p class="error">{{ $message }}</p>@enderror
                    @error('committee_roles.*')<p class="error">{{ $message }}</p>@enderror
                </div>
            </section>

            {{-- Étape 4 : informations complémentaires des participants --}}
            <section data-step="4" hidden aria-labelledby="step-4-title">
                <h3 id="step-4-title" class="wizard-title">4. Informations demandées aux participants</h3>
                <p class="text-sm text-muted">Questions posées lors de l’inscription (nom et e-mail sont déjà connus). Ajoutez en un clic les questions courantes pour ce type d’activité, puis adaptez-les. Une ligne sans intitulé est ignorée. Pour un choix, listez les options séparées par des virgules.</p>
                @php($fieldRows = array_pad($fieldRows, max(3, count($fieldRows) + 1), ['label' => '', 'type' => 'text', 'required' => false, 'options' => '']))
                <div class="field {{ $errors->has('fields') ? 'has-error' : '' }}">
                    <div class="suggestions" id="suggestions" aria-label="Questions proposées"></div>
                    <div id="field-rows">
                        @foreach($fieldRows as $i => $row)
                            <div class="form-grid question-row" data-row>
                                <div class="field"><input type="text" name="fields[{{ $i }}][label]" maxlength="160" placeholder="Question (ex. Taille de t-shirt)" aria-label="Question" value="{{ $row['label'] }}"></div>
                                <div class="field">
                                    <select name="fields[{{ $i }}][type]" aria-label="Type de réponse">
                                        @foreach(\App\Models\ClubActivity::FIELD_TYPES as $value => $label)<option value="{{ $value }}" @selected($row['type'] === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="field" data-options><input type="text" name="fields[{{ $i }}][options]" maxlength="400" placeholder="Options : S, M, L, XL" aria-label="Options du choix" value="{{ $row['options'] }}"></div>
                                <div class="field"><label class="check"><input type="checkbox" name="fields[{{ $i }}][required]" value="1" @checked($row['required'])> Obligatoire</label></div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" id="add-field" style="margin-top:8px">+ Ajouter une question</button>
                    @error('fields')<p class="error">{{ $message }}</p>@enderror
                </div>
            </section>
        </div>

        <div class="form-actions wizard-actions">
            <a href="{{ $editing ? route('admin.activities.show', $activity) : route('admin.activities.index') }}" class="btn btn-ghost">Annuler</a>
            <span class="grow"></span>
            <button type="button" class="btn btn-secondary" id="wizard-prev" hidden><x-admin.icon name="arrow-left" :size="16" /> Précédent</button>
            <button type="button" class="btn btn-primary" id="wizard-next">Suivant <x-admin.icon name="arrow-right" :size="16" /></button>
            <button type="submit" class="btn btn-primary" id="wizard-submit" @if(! $editing) hidden @endif><x-admin.icon name="check" :size="16" /> {{ $editing ? 'Enregistrer' : 'Créer l’activité' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('wizard');
        var editing = form.dataset.editing === '1';
        var sections = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
        var navItems = Array.prototype.slice.call(form.querySelectorAll('[data-nav]'));
        var prev = document.getElementById('wizard-prev');
        var next = document.getElementById('wizard-next');
        var submit = document.getElementById('wizard-submit');
        var current = parseInt(form.dataset.start, 10) || 1;
        var last = sections.length;

        function stepValid(number) {
            var section = sections[number - 1];
            var fields = section.querySelectorAll('input, select, textarea');
            for (var i = 0; i < fields.length; i++) {
                if (fields[i].closest('[hidden]') && fields[i].closest('[hidden]') !== section) continue;
                if (!fields[i].checkValidity()) { fields[i].reportValidity(); return false; }
            }
            return true;
        }

        function show(number) {
            current = number;
            sections.forEach(function (section, index) { section.hidden = index + 1 !== number; });
            navItems.forEach(function (item, index) {
                item.classList.toggle('is-current', index + 1 === number);
                item.classList.toggle('is-done', index + 1 < number);
            });
            prev.hidden = number === 1;
            next.hidden = number === last;
            submit.hidden = !editing && number !== last;
            window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
        }

        // Aller de l'avant exige que les étapes traversées soient valides ; revenir en arrière est toujours libre.
        function go(number) {
            if (number > current) {
                for (var step = current; step < number; step++) {
                    if (!stepValid(step)) { show(step); stepValid(step); return; }
                }
            }
            show(number);
        }

        next.addEventListener('click', function () { go(current + 1); });
        prev.addEventListener('click', function () { go(current - 1); });
        form.querySelectorAll('[data-go]').forEach(function (button) {
            button.addEventListener('click', function () { go(parseInt(button.dataset.go, 10)); });
        });

        // « Entrée » ne doit pas envoyer le formulaire à mi-parcours.
        form.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT' && event.target.type !== 'submit') {
                event.preventDefault();
                if (current < last && event.target.id !== 'committee-search') go(current + 1);
            }
        });

        form.addEventListener('submit', function (event) {
            for (var step = 1; step <= last; step++) {
                if (!stepValid(step)) { event.preventDefault(); show(step); stepValid(step); return; }
            }
        });

        /* ---------------------------------------------------------- Étape 1/2 : champs dépendants */
        var departments = @json($departments);
        var currentDepartment = @json((string) old('department_id', $activity->department_id));
        var clubId = @json($editing ? (string) $activity->club_id : null);
        var club = document.getElementById('f-club_id');
        var audience = document.getElementById('f-audience');
        var department = document.getElementById('f-department_id');

        function refreshDepartments() {
            var id = club ? club.value : clubId;
            var list = departments[id] || {};
            department.innerHTML = '<option value="">Choisir un département</option>';
            Object.keys(list).forEach(function (key) {
                var option = document.createElement('option');
                option.value = key; option.textContent = list[key];
                if (key === currentDepartment) option.selected = true;
                department.appendChild(option);
            });
            department.closest('.field').hidden = audience.value !== 'department';
        }

        if (club) club.addEventListener('change', function () { currentDepartment = ''; refreshDepartments(); loadCommittee(); });
        audience.addEventListener('change', refreshDepartments);
        refreshDepartments();

        // La nature (dans / hors thématique) n'a de sens que dans un défi.
        var challenge = document.getElementById('f-challenge_id');
        var nature = document.getElementById('nature-field');
        if (challenge && nature) {
            challenge.addEventListener('change', function () { nature.hidden = challenge.value === ''; });
        }

        /* ---------------------------------------------------------- Étape 3 : comité d'organisation */
        var box = document.getElementById('committee');
        var list = document.getElementById('committee-list');
        var hint = document.getElementById('committee-hint');
        var search = document.getElementById('committee-search');
        var roles = @json($committeeRoles);
        var selected = @json($committeeSelected);
        if (Array.isArray(selected)) selected = {};
        var people = @json($committeePeople);

        function el(tag, className, text) {
            var node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        }

        // Garde en mémoire la saisie en cours avant de redessiner la liste.
        function remember() {
            list.querySelectorAll('[data-person]').forEach(function (row) {
                var box = row.querySelector('input[type=checkbox]');
                if (box.checked) selected[box.value] = row.querySelector('select').value; else delete selected[box.value];
            });
        }

        function render() {
            remember();
            list.textContent = '';
            var needle = (search.value || '').trim().toLowerCase();

            if (!people.length) {
                hint.textContent = (club && club.value === '') ? 'Choisissez d’abord le club porteur (étape 1).' : 'Aucun adhérent validé ni titulaire de poste dans ce club pour le moment.';
                search.hidden = true;
                return;
            }

            hint.textContent = people.length + ' personne(s) disponible(s). Cochez celles qui font partie du comité.';
            search.hidden = people.length < 8;

            people.forEach(function (person) {
                var matches = needle === '' || person.name.toLowerCase().indexOf(needle) !== -1 || person.role.toLowerCase().indexOf(needle) !== -1;
                var checked = Object.prototype.hasOwnProperty.call(selected, person.id);
                if (!matches && !checked) return;

                var row = el('label', 'committee-row');
                row.setAttribute('data-person', '');
                var check = el('input');
                check.type = 'checkbox'; check.name = 'committee_ids[]'; check.value = person.id; check.checked = checked;
                var who = el('span', 'who');
                who.appendChild(el('strong', null, person.name));
                if (person.role) who.appendChild(el('small', null, person.role));
                var role = el('select');
                role.name = 'committee_roles[' + person.id + ']';
                role.setAttribute('aria-label', 'Rôle de ' + person.name);
                Object.keys(roles).forEach(function (key) {
                    var option = el('option', null, roles[key]);
                    option.value = key;
                    if ((selected[person.id] || 'member') === key) option.selected = true;
                    role.appendChild(option);
                });
                role.disabled = !checked;
                check.addEventListener('change', function () { role.disabled = !check.checked; });
                row.appendChild(check); row.appendChild(who); row.appendChild(role);
                list.appendChild(row);
            });
        }

        function loadCommittee() {
            if (editing || !club) return;
            remember();
            if (club.value === '') { people = []; selected = {}; render(); return; }

            hint.textContent = 'Chargement…';
            fetch(box.dataset.url + '?club=' + encodeURIComponent(club.value), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { if (!response.ok) throw new Error(); return response.json(); })
                .then(function (data) {
                    people = data;
                    var known = {};
                    data.forEach(function (person) { known[person.id] = true; });
                    Object.keys(selected).forEach(function (id) { if (!known[id]) delete selected[id]; });
                    render();
                })
                .catch(function () { people = []; render(); hint.textContent = 'Impossible de charger les personnes de ce club.'; });
        }

        search.addEventListener('input', render);
        if (editing) render(); else loadCommittee();

        /* ---------------------------------------------------------- Étape 4 : questions des participants */
        var suggestions = @json($questionSuggestions);
        var maxFields = {{ \App\Models\ClubActivity::MAX_FIELDS }};
        var rowsBox = document.getElementById('field-rows');
        var chips = document.getElementById('suggestions');
        var typeSelect = document.getElementById('f-type');

        function rows() { return rowsBox.querySelectorAll('[data-row]'); }
        function rowLabel(row) { return row.querySelector('input[name$="[label]"]').value.trim().toLowerCase(); }

        function syncOptions(row) {
            row.querySelector('[data-options]').hidden = row.querySelector('select').value !== 'choice';
        }

        function addRow() {
            var all = rows();
            if (all.length >= maxFields) return null;
            var clone = all[all.length - 1].cloneNode(true);
            clone.querySelectorAll('input,select').forEach(function (input) {
                if (input.type === 'checkbox') input.checked = false; else if (input.tagName === 'SELECT') input.selectedIndex = 0; else input.value = '';
                input.name = input.name.replace(/fields\[\d+\]/, 'fields[' + all.length + ']');
            });
            bindRow(clone);
            rowsBox.appendChild(clone);
            syncOptions(clone);
            return clone;
        }

        function bindRow(row) {
            row.querySelector('select').addEventListener('change', function () { syncOptions(row); });
            row.querySelector('input[name$="[label]"]').addEventListener('input', renderChips);
        }

        function useSuggestion(item) {
            var target = null;
            rows().forEach(function (row) { if (!target && rowLabel(row) === '') target = row; });
            target = target || addRow();
            if (!target) return;
            target.querySelector('input[name$="[label]"]').value = item.label;
            target.querySelector('select').value = item.type;
            target.querySelector('input[name$="[options]"]').value = (item.options || []).join(', ');
            target.querySelector('input[type=checkbox]').checked = !!item.required;
            syncOptions(target);
            renderChips();
        }

        function renderChips() {
            var type = typeSelect ? typeSelect.value : 'event';
            var present = {};
            rows().forEach(function (row) { present[rowLabel(row)] = true; });
            chips.textContent = '';
            var intro = el('span', 'text-sm text-muted', 'Questions courantes pour ce type d’activité :');
            chips.appendChild(intro);
            (suggestions[type] || []).forEach(function (item) {
                var chip = el('button', 'chip', '+ ' + item.label);
                chip.type = 'button';
                chip.disabled = !!present[item.label.toLowerCase()];
                chip.addEventListener('click', function () { useSuggestion(item); });
                chips.appendChild(chip);
            });
            var first = (suggestions[type] || [])[0];
            rows().forEach(function (row) {
                row.querySelector('input[name$="[label]"]').placeholder = 'Question (ex. ' + (first ? first.label : 'Taille de t-shirt') + ')';
            });
        }

        rows().forEach(function (row) { bindRow(row); syncOptions(row); });
        document.getElementById('add-field').addEventListener('click', function () { addRow(); });
        if (typeSelect) typeSelect.addEventListener('change', renderChips);
        renderChips();

        show(current);
    })();
</script>
@endpush
