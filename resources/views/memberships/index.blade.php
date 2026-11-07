@extends('layouts.base', ['title' => 'Mes adhésions | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mes adhésions</h1>
                <p class="max-w-2xl text-sm text-slate-600">Suivez vos adhésions à la Fondation, à ses entités et à ses clubs, et demandez à en rejoindre une nouvelle. Chaque demande est examinée par l’équipe.</p>
            </div>
            <button type="button" data-open-request class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-90"><i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> Demander une adhésion</button>
        </div>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        @if($errors->has('membership') && old('target') === null)
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert">{{ $errors->first('membership') }}</div>
        @endif

        @if($joinedMandate)
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">Vous avez rejoint le réseau au mandat <strong>{{ $joinedMandate->label }}</strong>. Déclarez votre participation aux mandats suivants ; les mandats antérieurs à votre arrivée sont grisés.</div>
        @endif

        <div>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft sm:p-6">
                <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse($memberships as $membership)
                        @php
                            [$statusLabel, $statusClass] = $statuses[$membership->status] ?? [$membership->status, 'bg-slate-100 text-slate-600'];
                        @endphp
                        <li class="flex flex-col rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800">{{ $membership->node?->title ?? (\App\Support\OrgScopes::label($membership->scope) ?: 'Fondation') }}</p>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ \App\Support\OrgScopes::label($membership->scope) }}
                                · demandée le {{ $membership->created_at->format('d/m/Y') }}
                                @if($membership->status === 'approved' && $membership->approved_at) · validée le {{ $membership->approved_at->format('d/m/Y') }}@endif
                            </p>
                            @if($membership->status === 'rejected' && filled($membership->rejection_reason))
                                <p class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-800">Motif : {{ $membership->rejection_reason }}</p>
                            @endif
                            @if($membership->status === 'pending')
                                <form method="post" action="{{ route('memberships.destroy', $membership) }}" class="mt-auto pt-2" onsubmit="return confirm('Retirer cette demande d\u2019adhésion ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Retirer ma demande</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Vous n’avez encore aucune adhésion. <button type="button" data-open-request class="font-semibold text-tlink-blue underline">Faites votre première demande</button>.</li>
                    @endforelse
                </ul>

                <div class="mt-8 border-t border-slate-100 pt-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Mes postes par mandat</h2>
                        @if($canEditPositions)
                            <a href="{{ route('admin.mandates.index') }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-tlink-blue hover:bg-slate-50">Gérer les mandats et leur statut</a>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Tous les mandats sont listés, y compris les précédents. Pour un ancien mandat, déclarez votre participation : un poste de membre est accepté directement, un poste de responsabilité (président, vice-président, assistant…) doit être approuvé par un administrateur.
                        @error('participation')<span class="mt-1 block font-semibold text-rose-700">{{ $message }}</span>@enderror
                    </p>

                    @php
                        $selectedMandate = $positionsByMandate->search(fn ($row) => $row['mandate']?->status === 'current');
                        $selectedMandate = $selectedMandate === false ? 0 : $selectedMandate;
                    @endphp

                    @if($positionsByMandate->isNotEmpty())
                        <div class="-mx-1 mt-5 overflow-x-auto px-1 pb-2" id="mandate-timeline">
                            <ol class="relative flex min-w-max items-start gap-2" role="tablist" aria-label="Chronologie des mandats">
                                <span class="absolute left-6 right-6 top-[0.7rem] h-0.5 bg-slate-200" aria-hidden="true"></span>
                                @foreach($positionsByMandate as $group)
                                    @php
                                        $tabMandate = $group['mandate'];
                                        $tabBefore = $tabMandate !== null && $joinedMandate !== null && $tabMandate->precedes($joinedMandate);
                                        $tabDot = match ($tabMandate?->status) {
                                            'current' => 'bg-emerald-500 ring-emerald-100',
                                            'upcoming' => 'bg-sky-500 ring-sky-100',
                                            default => 'bg-slate-400 ring-slate-100',
                                        };
                                    @endphp
                                    <li class="relative z-10" role="presentation">
                                        <button type="button" role="tab" id="mandate-tab-{{ $loop->index }}" data-mandate-tab="{{ $loop->index }}" aria-controls="mandate-panel-{{ $loop->index }}" aria-selected="{{ $loop->index === $selectedMandate ? 'true' : 'false' }}"
                                                @class(['group flex min-w-[6.5rem] flex-col items-center gap-2 rounded-xl px-3 py-1 text-center transition hover:bg-slate-50', 'opacity-60' => $tabBefore])>
                                            <span class="block h-4 w-4 rounded-full ring-4 transition group-aria-selected:scale-125 {{ $tabDot }}" aria-hidden="true"></span>
                                            <span class="text-xs font-semibold text-slate-500 group-aria-selected:text-tlink-blue">{{ $tabMandate ? $tabMandate->label : 'Hors mandat' }}</span>
                                            @if($tabMandate)
                                                <span class="text-[10px] text-slate-400">{{ \App\Models\Mandate::STATUSES[$tabMandate->status] ?? '' }}</span>
                                            @endif
                                        </button>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    <div class="mt-3">
                        @forelse($positionsByMandate as $group)
                            @php
                                $mandate = $group['mandate'];
                                $mandateStatus = $mandate ? (\App\Models\Mandate::STATUSES[$mandate->status] ?? null) : null;
                                $isPast = $mandate !== null && $mandate->status === 'closed';
                                $beforeArrival = $mandate !== null && $joinedMandate !== null && $mandate->precedes($joinedMandate);
                            @endphp
                            <div id="mandate-panel-{{ $loop->index }}" role="tabpanel" aria-labelledby="mandate-tab-{{ $loop->index }}" data-mandate-panel @if($loop->index !== $selectedMandate) hidden @endif
                                 @class(['rounded-2xl border border-slate-100 bg-slate-50/60 p-4', 'opacity-50' => $beforeArrival]) @if($beforeArrival) aria-disabled="true" data-before-arrival @endif>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-tlink-blue">
                                        {{ $mandate ? 'Mandat '.$mandate->label : 'Hors mandat' }}
                                        @if($mandate && filled($mandate->bureau_name))<span class="text-xs font-normal text-slate-500">· {{ $mandate->bureau_name }}</span>@endif
                                        @if($mandateStatus)<span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $mandate->status === 'current' ? 'bg-emerald-50 text-emerald-700' : ($mandate->status === 'upcoming' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600') }}">{{ $mandateStatus }}</span>@endif
                                    </p>
                                    @if($isPast && $beforeArrival)
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Avant mon arrivée</span>
                                    @elseif($isPast)
                                        <button type="button" data-open-past data-mandate-id="{{ $mandate->id }}" data-mandate-label="{{ $mandate->label }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-tlink-blue hover:bg-slate-50">Ajouter ma participation</button>
                                    @endif
                                </div>
                                @if($group['positions']->isEmpty() && $group['participations']->isEmpty())
                                    <p class="mt-1 text-xs text-slate-400">Aucun poste sur ce mandat.</p>
                                @endif
                                <ul class="mt-2 space-y-2">
                                    @foreach($group['participations'] as $participation)
                                        @php
                                            [$pLabel, $pClass] = \App\Models\MandateParticipation::STATUSES[$participation->status] ?? [$participation->status, 'bg-slate-100 text-slate-600'];
                                        @endphp
                                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 px-4 py-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800">{{ $participation->position_title ?? 'Membre' }} <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $pClass }}">{{ $pLabel }}</span></p>
                                                <p class="text-xs text-slate-500">{{ $participation->node?->title ?? \App\Support\OrgScopes::label($participation->scope) }} · {{ \App\Support\OrgScopes::label($participation->scope) }}</p>
                                                @if($participation->status === 'rejected' && filled($participation->rejection_reason))
                                                    <p class="mt-1 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-800">Motif : {{ $participation->rejection_reason }}</p>
                                                @endif
                                            </div>
                                            <form method="post" action="{{ route('memberships.past.destroy', $participation) }}" onsubmit="return confirm('Retirer cette participation ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">{{ $participation->status === 'pending' ? 'Retirer ma demande' : 'Retirer' }}</button>
                                            </form>
                                        </li>
                                    @endforeach
                                    @foreach($group['positions'] as $assignment)
                                        @php
                                            $ongoing = $assignment->ends_at === null || $assignment->ends_at->toDateString() >= now()->toDateString();
                                            $detail = collect([$assignment->node?->subtitle, $assignment->person?->job_title])->filter()->unique()->implode(' · ');
                                        @endphp
                                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 px-4 py-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800">{{ $assignment->node?->title ?? 'Poste supprimé' }}</p>
                                                @if($detail !== '')<p class="text-xs text-slate-500">{{ $detail }}</p>@endif
                                                <p class="text-xs text-slate-400">
                                                    {{ $assignment->starts_at?->format('d/m/Y') ?? '—' }} → {{ $assignment->ends_at?->format('d/m/Y') ?? 'aujourd’hui' }}
                                                    · {{ $ongoing ? 'En poste' : 'Terminé' }}
                                                </p>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @empty
                            <p class="rounded-2xl bg-slate-50 px-4 py-4 text-center text-sm text-slate-500">Aucun mandat n’est encore enregistré.</p>
                        @endforelse
                    </div>
                </div>
            </section>

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
                                    <option value="scope:{{ $slug }}" @selected(old('target') === 'scope:'.$slug)>{{ $label }}</option>
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
                        <option value="scope:{{ $slug }}">{{ $label }}</option>
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

<script>
    // Timeline horizontale : un clic sur un mandat affiche son panneau.
    (function () {
        var tabs = document.querySelectorAll('[data-mandate-tab]');
        var panels = document.querySelectorAll('[data-mandate-panel]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (other) { other.setAttribute('aria-selected', other === tab ? 'true' : 'false'); });
                panels.forEach(function (panel, index) { panel.hidden = String(index) !== tab.dataset.mandateTab; });
            });
        });
        var track = document.getElementById('mandate-timeline');
        var selected = document.querySelector('[data-mandate-tab][aria-selected="true"]');
        if (track && selected) { track.scrollLeft = Math.max(0, selected.parentNode.offsetLeft - 24); }
    })();
</script>
@endsection
