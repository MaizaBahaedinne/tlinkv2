@extends('layouts.base', ['title' => 'Mon intégration | T.Link'])

@section('content')
@php
    $fieldClass = 'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue';
    $buttonClass = 'rounded-xl bg-tlink-blue px-6 py-2.5 text-sm font-semibold text-white hover:opacity-90';
    $stepKeys = array_keys($steps);
    $user = auth()->user();
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.integration-header')

    <main class="app-container max-w-3xl py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Bienvenue {{ $user->first_name }}, finalisons votre intégration</h1>
            <p class="mt-1 text-sm text-slate-600">Ces étapes se suivent dans l’ordre. L’application s’ouvre dès que votre adhésion ou votre poste du mandat en cours est approuvé par l’un de vos supérieurs.</p>
        </div>

        <ol class="mt-5 grid gap-2 sm:grid-cols-5" aria-label="Étapes">
            @foreach($steps as $key => $label)
                @php $index = array_search($key, $stepKeys, true) + 1; @endphp
                <li class="rounded-2xl border px-3 py-3 text-xs font-semibold {{ $done[$key] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($key === $step ? 'border-tlink-blue bg-white text-tlink-blue' : 'border-slate-200 bg-slate-50 text-slate-400') }}" @if($key === $step) aria-current="step" @endif>
                    <span class="block text-base">{{ $done[$key] ? '✓' : $index }}</span>
                    {{ $label }}
                </li>
            @endforeach
        </ol>

        @if(session('status'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <section class="mt-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:p-8">
            @if($step === 'email')
                <h2 class="font-display text-lg font-semibold text-slate-900">Confirmez votre adresse e-mail</h2>
                <p class="mt-1 text-sm text-slate-600">Un lien de confirmation a été envoyé à <strong>{{ $user->email }}</strong>. Ouvrez-le pour continuer (pensez à vérifier vos courriers indésirables).</p>

                <form method="post" action="{{ route('verification.send') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="{{ $buttonClass }}">Renvoyer le lien</button>
                </form>

                <form method="post" action="{{ route('integration.email') }}" class="mt-6 border-t border-slate-100 pt-5">
                    @csrf
                    <label for="email" class="text-sm font-semibold text-slate-700">Mauvaise adresse ? Corrigez-la</label>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email', $user->email) }}" class="{{ $fieldClass }} sm:max-w-sm">
                        <button type="submit" class="rounded-xl border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Mettre à jour</button>
                    </div>
                    @error('email')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                </form>

            @elseif($step === 'info_session')
                <h2 class="font-display text-lg font-semibold text-slate-900">Session d’information obligatoire</h2>
                <p class="mt-1 text-sm text-slate-600">Regardez la vidéo de présentation de la Fondation puis réussissez le quiz pour valider votre compte.</p>
                <a href="{{ route('info-session.show') }}" class="{{ $buttonClass }} mt-5 inline-block">Commencer la session</a>

            @elseif($step === 'history')
                <h2 class="font-display text-lg font-semibold text-slate-900">Ancien ou nouveau membre ?</h2>
                <p class="mt-1 text-sm text-slate-600">Dites-nous si vous avez déjà fait partie du réseau. Les anciens membres reconstituent leur historique sur les mandats précédents ; le mandat en cours se traite à la dernière étape.</p>
                <a href="{{ route('onboarding.history') }}" class="{{ $buttonClass }} mt-5 inline-block">Répondre</a>

            @elseif($step === 'profile')
                <h2 class="font-display text-lg font-semibold text-slate-900">Complétez votre profil</h2>
                <p class="mt-1 text-sm text-slate-600">Il reste à renseigner :</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
                    @foreach($missingProfile as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('profile.show') }}" class="{{ $buttonClass }} mt-5 inline-block">Compléter mon profil</a>

            @else
                <h2 class="font-display text-lg font-semibold text-slate-900">
                    Adhésion ou poste pour le mandat {{ $mandate?->label }}
                </h2>

                @if($placement['state'] === 'pending')
                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                        @if($placement['membership'])
                            <p class="font-semibold">
                                {{ $placement['membership']->position ? 'Candidature au poste « '.$placement['membership']->position->title.' »' : 'Demande d’adhésion' }}
                                — {{ $placement['membership']->node?->title ?? \App\Support\OrgScopes::label($placement['membership']->scope) }}
                            </p>
                            <p class="mt-1">En attente de validation{{ $placement['reviewer'] ? ' par '.$placement['reviewer']->name : '' }}. Vous serez notifié(e) dès la décision ; l’application s’ouvrira alors automatiquement.</p>
                            <form method="post" action="{{ route('memberships.destroy', $placement['membership']) }}" class="mt-3" onsubmit="return confirm('Retirer cette demande ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-semibold text-rose-700 hover:underline">Retirer ma demande</button>
                            </form>
                        @else
                            <p class="font-semibold">Votre candidature par lien d’inscription est en cours d’examen.</p>
                            <p class="mt-1">L’application s’ouvrira dès qu’elle sera approuvée.</p>
                        @endif
                    </div>
                @else
                    @if($placement['state'] === 'rejected')
                        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <p class="font-semibold">Votre dernière demande a été refusée.</p>
                            @if($placement['membership']?->rejection_reason)<p class="mt-1">Motif : {{ $placement['membership']->rejection_reason }}</p>@endif
                            <p class="mt-1">Vous pouvez en envoyer une nouvelle.</p>
                        </div>
                    @endif

                    <p class="mt-2 text-sm text-slate-600">Choisissez où vous souhaitez vous engager pour ce mandat : une simple adhésion, ou une candidature à un poste. Elle sera examinée par l’un de vos supérieurs.</p>

                    <form id="placement-form" method="post" action="{{ route('memberships.store') }}" data-positions-url="{{ route('memberships.positions') }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="target" class="text-sm font-semibold text-slate-700">Entité ou club</label>
                            <select id="target" name="target" required class="{{ $fieldClass }}">
                                <option value="">Choisir…</option>
                                @foreach($scopes as $slug => $label)
                                    <option value="scope:{{ $slug }}" @selected(old('target') === 'scope:'.$slug)>{{ $label }}</option>
                                @endforeach
                                @foreach($clubs as $slug => $group)
                                    <optgroup label="Clubs — {{ $scopes[$slug] ?? $slug }}">
                                        @foreach($group as $club)
                                            <option value="club:{{ $club->id }}" @selected(old('target') === 'club:'.$club->id)>{{ $club->title }}{{ filled($club->subtitle) ? ' — '.$club->subtitle : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('target')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="position_id" class="text-sm font-semibold text-slate-700">Poste visé <span class="font-normal text-slate-400">(facultatif : sans poste, simple adhésion)</span></label>
                            <select id="position_id" name="position_id" class="{{ $fieldClass }}">
                                <option value="">Simple adhésion (sans poste)</option>
                            </select>
                            <p id="position-hint" class="mt-1 hidden text-xs text-amber-700">Ce poste accorde des droits : seul un administrateur global peut l’attribuer.</p>
                            @error('position_id')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="motivation_letter" class="text-sm font-semibold text-slate-700">Lettre de motivation</label>
                            <textarea id="motivation_letter" name="motivation_letter" rows="5" required minlength="{{ $motivationMin }}" maxlength="{{ $motivationMax }}" class="{{ $fieldClass }}">{{ old('motivation_letter') }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Au moins {{ $motivationMin }} caractères.</p>
                            @error('motivation_letter')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                            @error('cv')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="{{ $buttonClass }}">Envoyer ma demande</button>
                        </div>
                    </form>
                @endif
            @endif
        </section>

        <p class="mt-4 text-xs text-slate-500">Un souci ? Contactez l’équipe de la Fondation depuis le site officiel.</p>
    </main>
</div>

@if($step === 'placement' && $placement['state'] !== 'pending')
<script>
    (function () {
        var form = document.getElementById('placement-form');
        var target = document.getElementById('target');
        var select = document.getElementById('position_id');
        var hint = document.getElementById('position-hint');
        var rights = {};

        target.addEventListener('change', function () {
            select.innerHTML = '<option value="">Simple adhésion (sans poste)</option>';
            hint.classList.add('hidden');
            rights = {};

            if (!target.value) { return; }

            fetch(form.dataset.positionsUrl + '?target=' + encodeURIComponent(target.value), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    (payload.positions || []).forEach(function (position) {
                        var option = document.createElement('option');
                        option.value = position.id;
                        option.textContent = position.title + (position.rights ? ' (droits d’administration)' : '');
                        rights[position.id] = position.rights;
                        select.appendChild(option);
                    });
                });
        });

        select.addEventListener('change', function () {
            hint.classList.toggle('hidden', !rights[select.value]);
        });
    })();
</script>
@endif
@endsection
