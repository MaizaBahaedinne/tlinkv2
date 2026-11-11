@extends('layouts.base', ['title' => 'Mes adhésions | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        @include('memberships.partials.journey')

        <div>
            <dialog id="request-modal" class="m-auto max-h-[92vh] w-[calc(100%-1.5rem)] max-w-lg overflow-y-auto rounded-3xl p-0 shadow-soft backdrop:bg-slate-900/50" aria-labelledby="request-title">
                <form method="post" action="{{ route('memberships.store') }}" class="space-y-4 p-5 sm:p-6">
                    @csrf
                    <div class="flex items-start justify-between gap-3">
                        <h2 id="request-title" class="font-display text-lg font-semibold text-slate-900">Demander une adhésion</h2>
                        <button type="button" data-close-request class="text-slate-400 hover:text-slate-600" aria-label="Fermer">✕</button>
                    </div>

                    <div>
                        <label for="target" class="text-sm font-semibold text-slate-700">Où souhaitez-vous adhérer ?</label>
                        <select id="target" name="target" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                            <option value="">Choisir…</option>
                            <optgroup label="Entités de la Fondation">
                                @foreach($scopes as $slug => $label)
                                    @php $open = \App\Support\OrgScopes::openForMembership($slug); @endphp
                                    <option value="scope:{{ $slug }}" @selected(old('target') === 'scope:'.$slug) @disabled(! $open)>{{ $label }}{{ $open ? '' : ' (indisponible)' }}</option>
                                @endforeach
                            </optgroup>
                            @foreach($clubs as $slug => $list)
                                <optgroup label="Clubs · {{ $scopes[$slug] ?? $slug }}">
                                    @foreach($list as $club)
                                        <option value="club:{{ $club->id }}" @selected(old('target') === 'club:'.$club->id)>{{ $club->title }}{{ filled($club->subtitle) ? ' — '.$club->subtitle : '' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('target')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="motivation_letter" class="text-sm font-semibold text-slate-700">Lettre de motivation</label>
                        <textarea id="motivation_letter" name="motivation_letter" rows="7" required minlength="{{ $motivationMin }}" maxlength="{{ $motivationMax }}" placeholder="Présentez-vous et expliquez pourquoi vous souhaitez nous rejoindre." class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">{{ old('motivation_letter') }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">{{ $motivationMin }} caractères minimum.</p>
                        @error('motivation_letter')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    @if($missingCv !== [])
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-xs text-amber-900">
                            <p class="font-semibold">Votre CV est incomplet.</p>
                            <p class="mt-1">Ajoutez dans votre profil : {{ implode(', ', $missingCv) }}.</p>
                            <a href="{{ route('profile.show') }}" class="mt-2 inline-block font-semibold text-tlink-blue underline">Compléter mon CV</a>
                        </div>
                    @else
                        <p class="rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800">Votre CV est complet : il sera transmis avec votre demande. <a href="{{ route('profile.show') }}" class="font-semibold underline">Le consulter</a></p>
                    @endif
                    @error('cv')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    @error('membership')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror

                    <button type="submit" @disabled($missingCv !== []) class="w-full rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">Envoyer ma demande</button>
                    <p class="text-xs text-slate-500">Vous serez prévenu(e) dans vos notifications dès que votre demande sera traitée.</p>
                </form>
            </dialog>
        </div>
    </div>
</div>

@php
    $pastBag = $errors->getBag('past');
    $reopen = $pastBag->any() && old('mandate_id')
        ? [
            'id' => (string) old('mandate_id'),
            'label' => optional($positionsByMandate->first(fn ($row) => (string) ($row['mandate']?->id) === (string) old('mandate_id')))['mandate']?->label,
            'target' => (string) old('past_target'),
            'position' => (string) old('position_id'),
        ]
        : null;
@endphp

<dialog id="past-modal" class="m-auto max-h-[92vh] w-[calc(100%-1.5rem)] max-w-lg overflow-y-auto rounded-3xl p-0 shadow-soft backdrop:bg-slate-900/50">
    <form id="past-form" method="post" action="{{ route('memberships.past') }}" data-positions-url="{{ route('memberships.positions') }}" class="space-y-4 p-6">
        @csrf
        <input type="hidden" name="mandate_id" id="past-mandate-id" value="{{ old('mandate_id') }}">

        <div class="flex items-start justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-slate-900">Ma participation · mandat <span id="past-mandate-label"></span></h2>
            <button type="button" data-close-past class="text-slate-400 hover:text-slate-600" aria-label="Fermer">✕</button>
        </div>
        <p class="text-xs text-slate-500">Mandat précédent : un poste de membre est enregistré directement, sans validation. Un poste de responsabilité (président, vice-président, assistant…) est envoyé à un administrateur, qui doit l’approuver.</p>

        <div>
            <label for="past-target" class="text-sm font-semibold text-slate-700">Partie rejointe</label>
            <select id="past-target" name="past_target" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                <option value="">Choisir…</option>
                <optgroup label="Entités de la Fondation">
                    @foreach($scopes as $slug => $label)
                        @php $open = \App\Support\OrgScopes::openForMembership($slug); @endphp
                        <option value="scope:{{ $slug }}" @disabled(! $open)>{{ $label }}{{ $open ? '' : ' (indisponible)' }}</option>
                    @endforeach
                </optgroup>
                @foreach($pastClubs as $slug => $list)
                    <optgroup label="Clubs · {{ $scopes[$slug] ?? $slug }}">
                        @foreach($list as $club)
                            <option value="club:{{ $club->id }}">{{ $club->title }}{{ filled($club->subtitle) ? ' — '.$club->subtitle : '' }}{{ $club->status !== 'active' ? ' (inactif)' : '' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @if($pastBag->has('past_target'))<p class="mt-1 text-xs font-semibold text-rose-700">{{ $pastBag->first('past_target') }}</p>@endif
        </div>

        <div>
            <label for="past-position" class="text-sm font-semibold text-slate-700">Poste de membre</label>
            <select id="past-position" name="position_id" disabled class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue disabled:bg-slate-50 disabled:text-slate-400">
                <option value="">Choisissez d’abord la partie…</option>
            </select>
            <p class="mt-1 text-xs text-slate-500">Les postes disponibles se chargent après le choix de la partie.</p>
            @if($pastBag->has('position_id'))<p class="mt-1 text-xs font-semibold text-rose-700">{{ $pastBag->first('position_id') }}</p>@endif
            @if($pastBag->has('mandate_id'))<p class="mt-1 text-xs font-semibold text-rose-700">{{ $pastBag->first('mandate_id') }}</p>@endif
        </div>

        <div class="flex justify-end gap-2">
            <button type="button" data-close-past class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Annuler</button>
            <button type="submit" class="rounded-xl bg-tlink-blue px-5 py-2 text-sm font-semibold text-white hover:opacity-90">Enregistrer</button>
        </div>
    </form>
</dialog>

<script>
    (function () {
        var modal = document.getElementById('past-modal');
        if (!modal) { return; }

        var form = document.getElementById('past-form');
        var target = document.getElementById('past-target');
        var position = document.getElementById('past-position');
        var idInput = document.getElementById('past-mandate-id');
        var label = document.getElementById('past-mandate-label');
        var placeholder = 'Choisissez d’abord la partie…';

        function setOptions(text, disabled) {
            position.innerHTML = '';
            var option = document.createElement('option');
            option.value = '';
            option.textContent = text;
            position.appendChild(option);
            position.disabled = disabled;
        }

        function loadPositions(selected) {
            if (!target.value) { setOptions(placeholder, true); return Promise.resolve(); }
            setOptions('Chargement…', true);

            return fetch(form.dataset.positionsUrl + '?target=' + encodeURIComponent(target.value), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { if (!response.ok) { throw new Error('http'); } return response.json(); })
                .then(function (data) {
                    setOptions(data.positions.length ? 'Simple membre (sans poste précis)' : 'Aucun poste dans cette partie', false);
                    data.positions.forEach(function (item) {
                        var option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.title + (item.subtitle ? ' — ' + item.subtitle : '') + (item.leader ? ' (responsabilité : soumis à validation)' : '');
                        if (String(item.id) === String(selected)) { option.selected = true; }
                        position.appendChild(option);
                    });
                })
                .catch(function () { setOptions('Impossible de charger les postes', true); });
        }

        target.addEventListener('change', function () { loadPositions(''); });

        document.querySelectorAll('[data-open-past]').forEach(function (button) {
            button.addEventListener('click', function () {
                idInput.value = button.dataset.mandateId;
                label.textContent = button.dataset.mandateLabel;
                target.value = '';
                setOptions(placeholder, true);
                modal.showModal();
            });
        });

        modal.querySelectorAll('[data-close-past]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });

        var reopen = @json($reopen);
        if (reopen) {
            idInput.value = reopen.id;
            label.textContent = reopen.label || '';
            target.value = reopen.target;
            loadPositions(reopen.position);
            modal.showModal();
        }
    })();
</script>

<script>
    // Demande d'adhésion en modale ; rouverte avec ses erreurs si l'envoi a été refusé.
    (function () {
        var modal = document.getElementById('request-modal');
        if (!modal) { return; }

        document.querySelectorAll('[data-open-request]').forEach(function (button) {
            button.addEventListener('click', function () { modal.showModal(); });
        });
        modal.querySelectorAll('[data-close-request]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
        modal.addEventListener('click', function (event) { if (event.target === modal) { modal.close(); } });

        @if(old('target') !== null && $errors->any())
            modal.showModal();
        @endif
    })();
</script>

@endsection
