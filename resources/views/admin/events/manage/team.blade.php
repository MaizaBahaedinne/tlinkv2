@extends('admin.layouts.app')

@section('title', 'Équipe OC — '.$event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Équipe d’organisation</h2>
                <div class="sub">Chaque rôle ouvre des droits de gestion précis sur cet événement.</div>
            </div>
        </div>

        @if($members->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Membre</th><th>Poste et droits</th><th class="hide-mobile">Droits ouverts</th><th></th></tr></thead>
                    <tbody>
                        @foreach($members as $member)
                            <tr>
                                <td><div class="cell-main">{{ $member->user?->name }}</div><div class="cell-sub">{{ $member->user?->email }}{{ $member->notes ? ' · '.$member->notes : '' }}</div></td>
                                <td>
                                    <form method="post" action="{{ route('admin.events.team.update', [$event, $member]) }}" class="inline-form" style="flex-wrap:wrap;gap:6px">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="notes" value="{{ $member->notes }}">
                                        <input name="job_title" value="{{ $member->job_title }}" maxlength="160" placeholder="Poste (texte libre)" aria-label="Poste">
                                        <select name="role" aria-label="Niveau de droits">
                                            @foreach($roles as $code => $label)<option value="{{ $code }}" @selected($member->role === $code)>{{ $label }}</option>@endforeach
                                        </select>
                                        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                    </form>
                                </td>
                                <td class="hide-mobile text-sm text-muted">{{ collect($roleAbilities[$member->role] ?? [])->map(fn ($ability) => $abilityLabels[$ability] ?? $ability)->implode(' · ') }}</td>
                                <td class="text-right">
                                    <form method="post" action="{{ route('admin.events.team.destroy', [$event, $member]) }}" onsubmit="return confirm('Retirer ce membre de l’équipe ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty icon="users" title="Équipe vide" text="Ajoutez les membres de l’équipe d’organisation et attribuez-leur un rôle." />
        @endif
    </article>

    <article class="card card-flush">
        <div class="card-head"><h2>Ajouter un membre</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.events.team.store', $event) }}" class="form-grid">
                @csrf
                <div class="field span-2" style="position:relative">
                    <label for="member-search">Membre <span class="req">*</span></label>
                    <input type="hidden" name="user_id" id="member-id" value="{{ old('user_id') }}">
                    <input type="search" id="member-search" autocomplete="off" placeholder="Rechercher par nom ou e-mail…" data-search-url="{{ route('admin.events.team.search', $event) }}">
                    <p class="hint" id="member-chosen">Tapez au moins 2 lettres puis choisissez un membre dans la liste.</p>
                    <ul id="member-results" role="listbox" hidden style="position:absolute;z-index:20;left:0;right:0;top:100%;margin:2px 0 0;padding:4px;list-style:none;background:#fff;border:1px solid #d9dee7;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);max-height:260px;overflow:auto"></ul>
                </div>
                <div class="field"><label>Poste (texte libre)</label><input name="job_title" maxlength="160" value="{{ old('job_title') }}" placeholder="Responsable logistique, Chef de projet…"></div>
                <div class="field">
                    <label>Niveau de droits <span class="req">*</span></label>
                    <select name="role" required>@foreach($roles as $code => $label)<option value="{{ $code }}" @selected(old('role') === $code)>{{ $label }}</option>@endforeach</select>
                    <p class="hint">Détermine ce que le membre peut gérer ; le poste n’est qu’un libellé.</p>
                </div>
                <div class="field span-2"><label>Note</label><input name="notes" maxlength="255" value="{{ old('notes') }}" placeholder="Mission, contact…"></div>
                <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter à l’équipe</button></div>
            </form>
        </div>
    </article>
@endsection

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('member-search');
        var list = document.getElementById('member-results');
        var hidden = document.getElementById('member-id');
        var chosen = document.getElementById('member-chosen');
        var timer = null;
        if (!input) { return; }

        function close() { list.hidden = true; list.innerHTML = ''; }

        function render(members) {
            list.innerHTML = '';
            if (!members.length) {
                var empty = document.createElement('li');
                empty.textContent = 'Aucun membre trouvé.';
                empty.style.padding = '8px';
                list.appendChild(empty);
            }
            members.forEach(function (member) {
                var item = document.createElement('li');
                item.setAttribute('role', 'option');
                item.style.cssText = 'padding:8px;cursor:pointer;border-radius:8px';
                item.textContent = member.name + ' — ' + member.email;
                item.addEventListener('mouseenter', function () { item.style.background = '#f1f5f9'; });
                item.addEventListener('mouseleave', function () { item.style.background = ''; });
                item.addEventListener('click', function () {
                    hidden.value = member.id;
                    input.value = member.name;
                    chosen.textContent = 'Sélectionné : ' + member.name + ' (' + member.email + ')';
                    close();
                });
                list.appendChild(item);
            });
            list.hidden = false;
        }

        input.addEventListener('input', function () {
            hidden.value = '';
            clearTimeout(timer);
            var term = input.value.trim();
            if (term.length < 2) { close(); return; }
            timer = setTimeout(function () {
                fetch(input.dataset.searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) { return response.ok ? response.json() : { members: [] }; })
                    .then(function (data) { render(data.members || []); })
                    .catch(close);
            }, 250);
        });

        document.addEventListener('click', function (event) {
            if (event.target !== input && !list.contains(event.target)) { close(); }
        });
    })();
</script>
@endpush
