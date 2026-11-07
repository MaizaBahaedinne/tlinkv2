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
                @php
                    $index = array_search($key, $stepKeys, true) + 1;
                    $isDone = (bool) $done[$key];
                    $isCurrent = ! $isDone && $key === $step;
                @endphp
                <li class="flex items-center gap-3 rounded-2xl border px-3 py-3 text-xs font-semibold sm:flex-col sm:items-start sm:gap-2 {{ $isDone ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($isCurrent ? 'border-tlink-blue bg-white text-tlink-blue' : 'border-slate-200 bg-slate-50 text-slate-400') }}" data-step="{{ $key }}" data-state="{{ $isDone ? 'done' : ($isCurrent ? 'current' : 'pending') }}" @if($isCurrent) aria-current="step" @endif>
                    @if($isDone)
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                        </span>
                    @else
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-sm {{ $isCurrent ? 'border-tlink-blue' : 'border-slate-300' }}" aria-hidden="true">{{ $index }}</span>
                    @endif
                    <span class="block">
                        <span class="block">{{ $label }}</span>
                        <span class="mt-0.5 block text-[11px] font-medium">
                            @if($isDone)<span class="sr-only">Étape </span>Terminée
                            @elseif($isCurrent)En cours
                            @else En attente
                            @endif
                        </span>
                    </span>
                </li>
            @endforeach
        </ol>

        @if(session('status'))
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <section class="mt-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:p-8">
            @if($step === 'email')
                <h2 class="font-display text-lg font-semibold text-slate-900">Confirmez votre adresse e-mail</h2>
                <p class="mt-1 text-sm text-slate-600">Un code à 6 chiffres a été envoyé à <strong>{{ $user->email }}</strong>. Saisissez-le ci-dessous : il est valable {{ \App\Support\EmailVerificationCodes::VALID_MINUTES }} minutes (pensez à vérifier vos courriers indésirables).</p>

                <form method="post" action="{{ route('verification.verify') }}" class="mt-4">
                    @csrf
                    <label for="code" class="text-sm font-semibold text-slate-700">Code de confirmation</label>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required autofocus placeholder="123456" value="{{ old('code') }}" class="{{ $fieldClass }} w-44 text-center font-mono text-lg tracking-[0.4em]">
                        <button type="submit" class="{{ $buttonClass }}">Valider</button>
                    </div>
                    @error('code')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                </form>

                <form method="post" action="{{ route('verification.send') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-tlink-blue hover:underline">Je n’ai rien reçu : renvoyer un code</button>
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
                @php
                    $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Natif'];
                    $degrees = ['bac', 'licence', 'bts', 'master', 'ingenieur', 'doctorat', 'autre'];
                    $educationRows = old('educations', $educations ?: [[]]);
                    $languageList = old('languages', $languageRows ?: [[]]);
                    $birth = old('birth_date', $profile?->birth_date?->toDateString());
                    $gender = old('gender', $profile?->gender);
                    $inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue';
                @endphp
                <h2 class="font-display text-lg font-semibold text-slate-900">Complétez votre profil</h2>
                <p class="mt-1 text-sm text-slate-600">Tout se fait ici, sans quitter l’intégration. Les expériences professionnelles et associatives sont facultatives : vous pourrez les ajouter plus tard.</p>

                @if($missingProfile !== [])
                    <p class="mt-3 text-sm font-semibold text-slate-700">Il reste à renseigner :</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-slate-700">
                        @foreach($missingProfile as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="post" action="{{ route('integration.profile') }}" enctype="multipart/form-data" class="mt-5 space-y-6">
                    @csrf

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-slate-900">Photo de profil *</legend>
                        <div class="flex flex-wrap items-center gap-4">
                            @if($profile?->photo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($profile->photo_path) }}" alt="Votre photo actuelle" class="h-16 w-16 rounded-full object-cover">
                            @endif
                            <input type="file" name="avatar" accept="image/*" class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold">
                        </div>
                        @if($errors->has('avatar'))<p class="text-sm font-semibold text-rose-700">{{ $errors->first('avatar') }}</p>@endif
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-slate-900">Identité et contact</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="gender" class="text-xs font-semibold text-slate-600">Genre *</label>
                                <select id="gender" name="gender" required class="{{ $inputClass }}">
                                    <option value="">Choisir…</option>
                                    <option value="female" @selected($gender === 'female')>Femme</option>
                                    <option value="male" @selected($gender === 'male')>Homme</option>
                                </select>
                                @if($errors->has('gender'))<p class="mt-1 text-sm font-semibold text-rose-700">{{ $errors->first('gender') }}</p>@endif
                            </div>
                            <div>
                                <label for="birth_date" class="text-xs font-semibold text-slate-600">Date de naissance *</label>
                                <input id="birth_date" name="birth_date" type="date" required max="{{ now()->toDateString() }}" value="{{ $birth }}" autocomplete="bday" class="{{ $inputClass }}">
                                @if($errors->has('birth_date'))<p class="mt-1 text-sm font-semibold text-rose-700">{{ $errors->first('birth_date') }}</p>@endif
                            </div>
                        </div>
                        <div>
                            <label for="address" class="text-xs font-semibold text-slate-600">Adresse *</label>
                            <input id="address" name="address" type="text" required maxlength="255" value="{{ old('address', $profile?->address) }}" autocomplete="street-address" class="{{ $inputClass }}">
                            @if($errors->has('address'))<p class="mt-1 text-sm font-semibold text-rose-700">{{ $errors->first('address') }}</p>@endif
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label for="governorate_id" class="text-xs font-semibold text-slate-600">Gouvernorat *</label>
                                <select id="governorate_id" name="governorate_id" required class="{{ $inputClass }}">
                                    <option value="">Choisir…</option>
                                    @foreach($governorates as $governorate)
                                        <option value="{{ $governorate->id }}" @selected((string) old('governorate_id', $profile?->governorate_id) === (string) $governorate->id)>{{ $governorate->name }}</option>
                                    @endforeach
                                </select>
                                @if($errors->has('governorate_id'))<p class="mt-1 text-sm font-semibold text-rose-700">{{ $errors->first('governorate_id') }}</p>@endif
                            </div>
                            <div>
                                <label for="delegation" class="text-xs font-semibold text-slate-600">Délégation *</label>
                                <input id="delegation" name="delegation" type="text" required maxlength="120" value="{{ old('delegation', $profile?->delegation) }}" class="{{ $inputClass }}">
                                @if($errors->has('delegation'))<p class="mt-1 text-sm font-semibold text-rose-700">{{ $errors->first('delegation') }}</p>@endif
                            </div>
                        </div>
                        <div>
                            <label for="facebook_url" class="text-xs font-semibold text-slate-600">Page Facebook *</label>
                            <input id="facebook_url" name="facebook_url" type="url" required maxlength="255" placeholder="https://www.facebook.com/votre.nom" value="{{ old('facebook_url', $profile?->facebook_url) }}" class="{{ $inputClass }}">
                            @if($errors->has('facebook_url'))<p class="mt-1 text-sm font-semibold text-rose-700">{{ $errors->first('facebook_url') }}</p>@endif
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-slate-900">Formation * <span class="font-normal text-slate-500">(au moins une)</span></legend>
                        <div id="education-list" class="space-y-3">
                            @foreach($educationRows as $i => $row)
                                <div class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                                    <select name="educations[{{ $i }}][degree_type]" class="{{ $inputClass }}">
                                        @foreach($degrees as $degree)
                                            <option value="{{ $degree }}" @selected(($row['degree_type'] ?? '') === $degree)>{{ ucfirst($degree) }}</option>
                                        @endforeach
                                    </select>
                                    <input name="educations[{{ $i }}][diploma_name]" value="{{ $row['diploma_name'] ?? '' }}" maxlength="180" placeholder="Nom du diplôme" class="{{ $inputClass }}">
                                    <input name="educations[{{ $i }}][school]" value="{{ $row['school'] ?? '' }}" maxlength="180" list="school-options" autocomplete="off" placeholder="École (choisissez dans la liste)" class="{{ $inputClass }} sm:col-span-2">
                                    <input type="date" name="educations[{{ $i }}][start_date]" value="{{ $row['start_date'] ?? '' }}" class="{{ $inputClass }}" aria-label="Début">
                                    <input type="date" name="educations[{{ $i }}][end_date]" value="{{ $row['end_date'] ?? '' }}" class="{{ $inputClass }}" aria-label="Fin">
                                </div>
                            @endforeach
                        </div>
                        <button type="button" data-add="education" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Ajouter une formation</button>
                        @if($errors->has('educations'))<p class="text-sm font-semibold text-rose-700">{{ $errors->first('educations') }}</p>@endif
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-slate-900">Compétences *</legend>
                        <textarea name="skills" rows="3" required maxlength="2000" placeholder="Mots-clés séparés par des virgules (ex. gestion de projet, Laravel, communication)" class="{{ $inputClass }}">{{ old('skills', $skillKeywords) }}</textarea>
                        @if($errors->has('skills'))<p class="text-sm font-semibold text-rose-700">{{ $errors->first('skills') }}</p>@endif
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-slate-900">Langues * <span class="font-normal text-slate-500">(au moins une)</span></legend>
                        <div id="language-list" class="space-y-3">
                            @foreach($languageList as $i => $row)
                                <div class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                                    <input name="languages[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="100" placeholder="Langue (ex. Français)" class="{{ $inputClass }}">
                                    <select name="languages[{{ $i }}][level]" class="{{ $inputClass }}">
                                        @foreach($levels as $level)
                                            <option value="{{ $level }}" @selected(($row['level'] ?? '') === $level)>{{ $level }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" data-add="language" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Ajouter une langue</button>
                        @if($errors->has('languages'))<p class="text-sm font-semibold text-rose-700">{{ $errors->first('languages') }}</p>@endif
                    </fieldset>

                    <datalist id="school-options">
                        @foreach($schoolOptions as $option)<option value="{{ $option }}"></option>@endforeach
                    </datalist>

                    <template id="tpl-education">
                        <div class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                            <select name="educations[__INDEX__][degree_type]" class="{{ $inputClass }}">
                                @foreach($degrees as $degree)<option value="{{ $degree }}">{{ ucfirst($degree) }}</option>@endforeach
                            </select>
                            <input name="educations[__INDEX__][diploma_name]" maxlength="180" placeholder="Nom du diplôme" class="{{ $inputClass }}">
                            <input name="educations[__INDEX__][school]" maxlength="180" list="school-options" autocomplete="off" placeholder="École (choisissez dans la liste)" class="{{ $inputClass }} sm:col-span-2">
                            <input type="date" name="educations[__INDEX__][start_date]" class="{{ $inputClass }}" aria-label="Début">
                            <input type="date" name="educations[__INDEX__][end_date]" class="{{ $inputClass }}" aria-label="Fin">
                        </div>
                    </template>
                    <template id="tpl-language">
                        <div class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                            <input name="languages[__INDEX__][name]" maxlength="100" placeholder="Langue (ex. Français)" class="{{ $inputClass }}">
                            <select name="languages[__INDEX__][level]" class="{{ $inputClass }}">
                                @foreach($levels as $level)<option value="{{ $level }}">{{ $level }}</option>@endforeach
                            </select>
                        </div>
                    </template>

                    <div class="flex justify-end">
                        <button type="submit" class="{{ $buttonClass }}">Enregistrer mon profil</button>
                    </div>
                </form>

            @else
                <h2 class="font-display text-lg font-semibold text-slate-900">
                    {{ $isAlumni ? 'Adhésion ou poste' : 'Adhésion à un club' }} pour le mandat {{ $mandate?->label }}
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

                    @if($isAlumni)
                        <p class="mt-2 text-sm text-slate-600">En tant qu’ancien membre, vous pouvez rejoindre un club ou une entité, et candidater à un poste. Votre demande sera examinée et validée par l’un de vos supérieurs.</p>
                    @else
                        <p class="mt-2 text-sm text-slate-600">En tant que nouveau membre, vous rejoignez un club pour ce mandat. Votre demande sera examinée par l’un de vos supérieurs ; les postes dans les entités sont réservés aux anciens membres.</p>
                    @endif

                    <form id="placement-form" method="post" action="{{ route('memberships.store') }}" data-positions-url="{{ route('memberships.positions') }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="target" class="text-sm font-semibold text-slate-700">{{ $isAlumni ? 'Entité ou club' : 'Club' }}</label>
                            <select id="target" name="target" required class="{{ $fieldClass }}">
                                <option value="">Choisir…</option>
                                @if($isAlumni)
                                    @foreach($scopes as $slug => $label)
                                        <option value="scope:{{ $slug }}" @selected(old('target') === 'scope:'.$slug)>{{ $label }}</option>
                                    @endforeach
                                @endif
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

                        @if($isAlumni)
                        <div>
                            <label for="position_id" class="text-sm font-semibold text-slate-700">Poste visé <span class="font-normal text-slate-400">(facultatif : sans poste, simple adhésion)</span></label>
                            <select id="position_id" name="position_id" class="{{ $fieldClass }}">
                                <option value="">Simple adhésion (sans poste)</option>
                            </select>
                            <p id="position-hint" class="mt-1 hidden text-xs text-amber-700">Ce poste accorde des droits : seul un administrateur global peut l’attribuer.</p>
                            @error('position_id')<p class="mt-1 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        @endif

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

@if($step === 'profile')
<script>
    (function () {
        var counter = 100;

        document.querySelectorAll('[data-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                var kind = button.dataset.add;
                var list = document.getElementById(kind + '-list');
                var template = document.getElementById('tpl-' + kind);

                list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(counter++)));
            });
        });
    })();
</script>
@endif

@if($step === 'placement' && $placement['state'] !== 'pending' && $isAlumni)
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
