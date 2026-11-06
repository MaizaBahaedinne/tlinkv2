@extends('layouts.base', ['title' => 'Bienvenue dans le réseau | T.Link'])

@section('content')
@php
    $steps = [1 => 'Ancien ou nouveau', 2 => 'Mon arrivée', 3 => 'Mon parcours'];
    $fieldClass = 'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue';
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Bienvenue dans le réseau Tunivisions</h1>
            <p class="mt-1 text-sm text-slate-600">Ton compte est validé. Ces quelques questions nous aident à reconstituer l’histoire de la Fondation et de ses membres : plus tu es précis, plus l’historique est complet.</p>
        </div>

        <ol class="mt-5 flex items-center gap-2 text-xs font-semibold" aria-label="Étapes">
            @foreach($steps as $number => $label)
                <li class="flex flex-1 items-center gap-2 {{ $number < $step ? 'text-emerald-700' : ($number === $step ? 'text-tlink-blue' : 'text-slate-400') }}" @if($number === $step) aria-current="step" @endif>
                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full {{ $number < $step ? 'bg-emerald-100' : ($number === $step ? 'bg-tlink-blue text-white' : 'bg-slate-200') }}">{{ $number < $step ? '✓' : $number }}</span>
                    <span class="hidden sm:inline">{{ $label }}</span>
                    @if($number < count($steps))<span class="h-px flex-1 bg-slate-200"></span>@endif
                </li>
            @endforeach
        </ol>

        @if(session('status'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <section class="mt-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:p-8">
            @if($step === 1)
                <h2 class="font-display text-lg font-semibold text-slate-900">Es-tu déjà passé par Tunivisions ?</h2>
                <form method="post" action="{{ route('onboarding.origin') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <button type="submit" name="origin" value="alumni" class="rounded-2xl border border-slate-200 p-5 text-left transition hover:border-tlink-blue hover:bg-slate-50">
                        <span class="block text-base font-semibold text-slate-900">Ancien membre</span>
                        <span class="mt-1 block text-sm text-slate-500">J’ai déjà fait partie du réseau lors d’un mandat précédent.</span>
                    </button>
                    <button type="submit" name="origin" value="new" class="rounded-2xl border border-slate-200 p-5 text-left transition hover:border-tlink-blue hover:bg-slate-50">
                        <span class="block text-base font-semibold text-slate-900">Nouveau membre</span>
                        <span class="mt-1 block text-sm text-slate-500">Je découvre Tunivisions ou je la rejoins ce mandat.</span>
                    </button>
                </form>
                @error('origin')<p class="mt-3 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror

            @elseif($step === 2)
                <h2 class="font-display text-lg font-semibold text-slate-900">Quand as-tu rejoint le réseau ?</h2>
                <p class="mt-1 text-sm text-slate-500">Indique le premier mandat où tu as intégré Tunivisions et par quel poste. Nous en faisons ta première adhésion ; tu compléteras les mandats suivants ensuite. Les mandats antérieurs à ton arrivée seront grisés.</p>

                @if($closedMandates->isEmpty())
                    <p class="mt-4 rounded-2xl bg-amber-50 px-4 py-4 text-sm text-amber-900">Aucun mandat précédent n’est encore enregistré. Si tu as rejoint pendant le mandat en cours, tu peux te déclarer nouveau membre.</p>
                @else
                    <form id="entry-form" method="post" action="{{ route('onboarding.entry') }}" data-positions-url="{{ route('memberships.positions') }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="mandate_id" class="text-sm font-semibold text-slate-700">Mandat d’arrivée</label>
                            <select id="mandate_id" name="mandate_id" required class="{{ $fieldClass }}">
                                <option value="">Choisir…</option>
                                @foreach($closedMandates as $mandate)
                                    <option value="{{ $mandate->id }}" @selected((string) old('mandate_id') === (string) $mandate->id)>Mandat {{ $mandate->label }}{{ filled($mandate->bureau_name) ? ' — '.$mandate->bureau_name : '' }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Tu as rejoint pendant le mandat en cours ? Reviens en arrière et choisis « Nouveau membre ».</p>
                            @error('mandate_id')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="past-scope" class="text-sm font-semibold text-slate-700">Partie rejointe</label>
                            <select id="past-scope" name="past_scope" required class="{{ $fieldClass }}">
                                <option value="">Choisir…</option>
                                @foreach($scopes as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('past_scope') === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('past_target')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="past-club" class="text-sm font-semibold text-slate-700">Club rejoint</label>
                            <select id="past-club" name="past_club" disabled class="{{ $fieldClass }} disabled:bg-slate-50 disabled:text-slate-400">
                                <option value="">Choisis d’abord la partie…</option>
                            </select>
                            @error('past_club')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="past-position" class="text-sm font-semibold text-slate-700">Poste occupé à ton arrivée</label>
                            <select id="past-position" name="position_id" disabled class="{{ $fieldClass }} disabled:bg-slate-50 disabled:text-slate-400">
                                <option value="">Choisis d’abord le club…</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Un poste de membre est enregistré directement. Un poste de responsabilité (président, vice-président, assistant…) doit être approuvé par un administrateur.</p>
                            @error('position_id')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <button type="submit" form="origin-new" class="text-sm font-semibold text-slate-500 underline hover:text-slate-700">Finalement, je suis un nouveau membre</button>
                            <button type="submit" class="rounded-xl bg-tlink-blue px-6 py-2.5 text-sm font-semibold text-white hover:opacity-90">Enregistrer ma première adhésion</button>
                        </div>
                    </form>
                @endif
                <form id="origin-new" method="post" action="{{ route('onboarding.origin') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="origin" value="new">
                </form>

            @else
                <h2 class="font-display text-lg font-semibold text-slate-900">Complète ton parcours depuis le mandat {{ $joined->label }}</h2>
                <p class="mt-1 text-sm text-slate-500">Ta première adhésion est enregistrée. Déclare maintenant ta participation à chacun des mandats suivants (partie rejointe et poste). Les mandats antérieurs à ton arrivée sont grisés.</p>

                <ul class="mt-5 space-y-2">
                    @foreach($closedMandates->sortBy('start_year') as $mandate)
                        @php
                            $before = $mandate->precedes($joined);
                            $rows = $declared->get($mandate->id, collect());
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border px-4 py-3 {{ $before ? 'border-slate-100 bg-slate-50 text-slate-400 opacity-60' : 'border-slate-200' }}" @if($before) aria-disabled="true" @endif>
                            <div>
                                <p class="text-sm font-semibold {{ $before ? 'text-slate-400' : 'text-slate-800' }}">Mandat {{ $mandate->label }}@if($mandate->id === $joined->id) <span class="ml-1 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700">Mon arrivée</span>@endif</p>
                                @if($before)
                                    <p class="text-xs">Avant mon arrivée dans le réseau</p>
                                @elseif($rows->isNotEmpty())
                                    <p class="text-xs text-slate-500">{{ $rows->map(fn ($row) => ($row->position_title ?? 'Membre').' · '.($row->node?->title ?? \App\Support\OrgScopes::label($row->scope)))->implode(' ; ') }}</p>
                                @else
                                    <p class="text-xs text-amber-700">À compléter</p>
                                @endif
                            </div>
                            @unless($before)
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $rows->isNotEmpty() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $rows->isNotEmpty() ? 'Déclaré' : 'À déclarer' }}</span>
                            @endunless
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                    <a href="{{ route('memberships.index') }}" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Compléter mes adhésions</a>
                    @if(\App\Support\Onboarding::applies(auth()->user()))
                        <a href="{{ route('integration.show') }}" class="rounded-xl bg-tlink-blue px-6 py-2.5 text-sm font-semibold text-white hover:opacity-90">Continuer mon intégration</a>
                    @endif
                </div>
            @endif
        </section>
    </main>
</div>

@if($step === 2 && $closedMandates->isNotEmpty())
<script>
    (function () {
        var form = document.getElementById('entry-form');
        var scope = document.getElementById('past-scope');
        var club = document.getElementById('past-club');
        var position = document.getElementById('past-position');
        var clubsByScope = @json($clubs->map(fn ($list) => $list->map(fn ($c) => ['id' => $c->id, 'title' => $c->title.(filled($c->subtitle) ? ' — '.$c->subtitle : '')])->values()));
        var selectedClub = @json((string) old('past_club'));
        var selectedPosition = @json((string) old('position_id'));

        function reset(select, text, disabled) {
            select.innerHTML = '';
            var option = document.createElement('option');
            option.value = '';
            option.textContent = text;
            select.appendChild(option);
            select.disabled = disabled;
            select.required = false;
        }

        function loadPositions(target, preselect) {
            reset(position, 'Chargement…', true);

            fetch(form.dataset.positionsUrl + '?target=' + encodeURIComponent(target), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { if (!response.ok) { throw new Error('http'); } return response.json(); })
                .then(function (data) {
                    reset(position, data.positions.length ? 'Simple membre (sans poste précis)' : 'Aucun poste dans cette partie', false);
                    data.positions.forEach(function (item) {
                        var option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.title + (item.subtitle ? ' — ' + item.subtitle : '') + (item.leader ? ' (responsabilité : soumis à validation)' : '');
                        if (String(item.id) === String(preselect)) { option.selected = true; }
                        position.appendChild(option);
                    });
                })
                .catch(function () { reset(position, 'Impossible de charger les postes', true); });
        }

        // Partie → club (obligatoire quand la partie a des clubs) → poste.
        function onScope(preselectClub, preselectPosition) {
            reset(position, 'Choisis d’abord le club…', true);

            if (!scope.value) { reset(club, 'Choisis d’abord la partie…', true); return; }

            var clubs = clubsByScope[scope.value] || [];

            if (!clubs.length) {
                reset(club, 'Aucun club dans cette partie', true);
                loadPositions('scope:' + scope.value, preselectPosition);
                return;
            }

            reset(club, 'Choisir le club…', false);
            club.required = true;
            clubs.forEach(function (item) {
                var option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.title;
                if (String(item.id) === String(preselectClub)) { option.selected = true; }
                club.appendChild(option);
            });

            if (club.value) { loadPositions('club:' + club.value, preselectPosition); }
        }

        scope.addEventListener('change', function () { onScope('', ''); });
        club.addEventListener('change', function () {
            if (club.value) { loadPositions('club:' + club.value, ''); } else { reset(position, 'Choisis d’abord le club…', true); }
        });

        if (scope.value) { onScope(selectedClub, selectedPosition); }
    })();
</script>
@endif
@endsection
