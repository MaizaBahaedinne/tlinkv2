@extends('auth.layout', [
    'title' => 'Compléter mon inscription : '.$event->title.' | T.Link',
    'heroTitle' => 'Complète ton inscription : '.$event->title.'.',
    'heroText' => 'Retrouve ta fiche avec ton numéro de téléphone, vérifie tes informations et ajoute ta photo et ta preuve de paiement.',
    'formWidth' => 'max-w-2xl',
])

@section('form')
@php
    $input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-base outline-none transition sm:text-sm focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-700';
    $legend = 'text-xs font-bold uppercase tracking-wider text-tlink-blue';
    $err = 'mt-1.5 text-xs text-rose-600';
    $fileInput = 'block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-tlink-blue file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-tlink-blue-700';
@endphp
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:p-10">
    <p class="{{ $legend }}">Inscription</p>
    <h1 class="mt-1 font-display text-3xl font-semibold text-slate-900">{{ $event->title }}</h1>
    @if ($event->starts_at)
        <p class="mt-1 text-sm text-slate-500">{{ $event->starts_at->format('d/m/Y H:i') }}@if ($event->location) · {{ $event->location }}@endif</p>
    @endif

    @if (session('error'))
        <p class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ session('error') }}</p>
    @endif

    @if ($done)
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-5 text-emerald-900" role="status">
            <p class="text-base font-semibold">Merci, ta fiche est complétée.</p>
            <p class="mt-1 text-sm">Ton inscription est enregistrée. L’équipe vérifie ta preuve de paiement et te confirmera ta place. Confirme aussi ton adresse e-mail avec le code reçu.</p>
        </div>
        @auth
            <a href="{{ route('member.events.show', $event) }}" class="mt-5 inline-flex rounded-xl bg-tlink-blue px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-tlink-blue/25 hover:bg-tlink-blue-700">Voir mon inscription</a>
        @endauth
    @elseif ($entry === null)
        <p class="mt-4 text-sm text-slate-600">Tu es déjà inscrit(e) à cet événement. Saisis le numéro de téléphone avec lequel tu t’es inscrit(e) pour retrouver ta fiche et la compléter.</p>

        <form class="mt-6 space-y-4" method="post" action="{{ route('event.complete.lookup', $event) }}">
            @csrf
            <div>
                <label class="{{ $label }}" for="phone">Numéro de téléphone *</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required maxlength="30" autocomplete="tel" autofocus placeholder="Ex. 20 123 456" class="{{ $input }}">
                @error('phone') <p class="{{ $err }}">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-tlink-blue/25 transition hover:bg-tlink-blue-700">Retrouver ma fiche</button>
        </form>
    @else
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            <span>Bonjour <strong>{{ $entry->fullName() }}</strong> · {{ $entry->phone }}@if ($release) · release « {{ $release->name }} »@endif</span>
            <form method="post" action="{{ route('event.complete.forget', $event) }}">
                @csrf
                <button type="submit" class="font-semibold underline">Ce n’est pas moi</button>
            </form>
        </div>
        @error('phone') <p class="{{ $err }}">{{ $message }}</p> @enderror

        <form id="completion-form" class="mt-6 space-y-8" method="post" action="{{ route('event.complete.store', $event) }}" enctype="multipart/form-data" data-positions-url="{{ route('event.complete.positions', $event) }}">
            @csrf

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Photo de profil *</legend>
                <div class="flex items-center gap-4">
                    <img id="avatar-preview" alt="" class="hidden h-20 w-20 rounded-full border border-slate-200 object-cover">
                    <span id="avatar-placeholder" class="grid h-20 w-20 place-items-center rounded-full border border-dashed border-slate-300 bg-slate-50 text-slate-400">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" @required(! $account?->profile?->photo_path) class="{{ $fileInput }}">
                        <p class="mt-1 text-xs text-slate-500">Prends un selfie ou choisis une photo (JPG, PNG, WebP, 5 Mo max) où l’on voit bien ton visage.</p>
                        @error('avatar') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Identité</legend>
                <p class="text-xs text-slate-500">Vérifie l’orthographe : nom de famille et prénom sont parfois inversés dans la liste de départ.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}" for="last_name">Nom de famille *</label>
                        <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $entry->last_name) }}" required maxlength="120" autocomplete="family-name" class="{{ $input }}">
                        @error('last_name') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="first_name">Prénom *</label>
                        <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $entry->first_name) }}" required maxlength="120" autocomplete="given-name" class="{{ $input }}">
                        @error('first_name') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="gender">Sexe *</label>
                        <select id="gender" name="gender" required class="{{ $input }}">
                            <option value="">Choisir…</option>
                            <option value="female" @selected(old('gender') === 'female')>Femme</option>
                            <option value="male" @selected(old('gender') === 'male')>Homme</option>
                        </select>
                        @error('gender') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="birth_date">Date de naissance *</label>
                        <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}" required max="{{ now()->toDateString() }}" autocomplete="bday" class="{{ $input }}">
                        @error('birth_date') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="phone_display">Téléphone</label>
                        <input id="phone_display" type="tel" value="{{ $entry->phone }}" readonly class="{{ $input }} bg-slate-50 text-slate-500">
                    </div>
                    @unless ($account)
                        <div>
                            <label class="{{ $label }}" for="email">E-mail *</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="toi@exemple.com" class="{{ $input }}">
                            @error('email') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                    @endunless
                </div>
            </fieldset>

            @unless ($account)
                <fieldset class="space-y-4">
                    <legend class="{{ $legend }}">Mot de passe de ton compte</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @include('auth.partials.password-field', ['autocomplete' => 'new-password', 'label' => 'Mot de passe *'])
                        @include('auth.partials.password-field', ['id' => 'password_confirmation', 'label' => 'Confirmation *', 'autocomplete' => 'new-password', 'toggle' => false])
                    </div>
                </fieldset>
            @endunless

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Mon rôle actuel dans la Fondation</legend>
                <p class="text-xs text-slate-500">Facultatif : laisse vide si tu n’as pas de rôle.</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="{{ $label }}" for="cur-scope">Périmètre</label>
                        <select id="cur-scope" name="scope" class="{{ $input }}">
                            <option value="">Aucun</option>
                            @foreach ($scopes as $slug => $name)
                                <option value="{{ $slug }}" @selected(old('scope') === $slug)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('scope') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="cur-club">Club</label>
                        <select id="cur-club" name="club_id" disabled class="{{ $input }} disabled:bg-slate-50 disabled:text-slate-400"></select>
                        @error('club_id') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="cur-position">Poste</label>
                        <select id="cur-position" name="position_id" disabled class="{{ $input }} disabled:bg-slate-50 disabled:text-slate-400"></select>
                        @error('position_id') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Membre Tunivisions depuis</legend>
                <div>
                    <label class="{{ $label }}" for="joined_mandate_id">Mandat d’arrivée</label>
                    <select id="joined_mandate_id" name="joined_mandate_id" class="{{ $input }}">
                        <option value="">Je ne suis pas (encore) membre</option>
                        @foreach ($mandates as $mandate)
                            <option value="{{ $mandate->id }}" @selected((string) old('joined_mandate_id') === (string) $mandate->id)>Mandat {{ $mandate->label }}{{ filled($mandate->bureau_name) ? ' — '.$mandate->bureau_name : '' }}</option>
                        @endforeach
                    </select>
                    @error('joined_mandate_id') <p class="{{ $err }}">{{ $message }}</p> @enderror
                </div>
                <div id="join-fields" class="hidden">
                    <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="{{ $label }}" for="join-scope">Partie rejointe</label>
                        <select id="join-scope" name="joined_scope" class="{{ $input }}">
                            <option value="">Choisir…</option>
                            @foreach ($scopes as $slug => $name)
                                <option value="{{ $slug }}" @selected(old('joined_scope') === $slug)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('joined_scope') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="join-club">Club</label>
                        <select id="join-club" name="joined_club_id" disabled class="{{ $input }} disabled:bg-slate-50 disabled:text-slate-400"></select>
                        @error('joined_club_id') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="join-position">Poste dans ce mandat</label>
                        <select id="join-position" name="joined_position_id" disabled class="{{ $input }} disabled:bg-slate-50 disabled:text-slate-400"></select>
                        @error('joined_position_id') <p class="{{ $err }}">{{ $message }}</p> @enderror
                    </div>
                    </div>
                </div>
                <p id="join-hint" class="hidden text-xs text-slate-500">Un poste de responsabilité (président, vice-président…) est soumis à la validation d’un administrateur.</p>
            </fieldset>

            @if ($amount > 0)
                <fieldset class="space-y-4">
                    <legend class="{{ $legend }}">Preuve de paiement</legend>
                    <p class="text-sm text-slate-700">Montant à régler : <strong>{{ rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',') }} TND</strong>@if ($release) (release « {{ $release->name }} »)@endif.</p>
                    @if ($methods !== [])
                        <div>
                            <label class="{{ $label }}" for="method">Moyen de paiement utilisé *</label>
                            <select id="method" name="method" class="{{ $input }}">
                                <option value="">Choisir…</option>
                                @foreach ($methods as $code => $instructions)
                                    <option value="{{ $code }}" @selected(old('method') === $code)>{{ $methodLabels[$code] ?? $code }}</option>
                                @endforeach
                            </select>
                            @error('method') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            @foreach ($methods as $code => $instructions)
                                @if (filled($instructions))
                                    <p class="mt-1.5 hidden whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" data-method-help="{{ $code }}">{{ $instructions }}</p>
                                @endif
                            @endforeach
                        </div>
                        <div>
                            <label class="{{ $label }}" for="reference">Référence de la transaction</label>
                            <input id="reference" name="reference" type="text" value="{{ old('reference') }}" maxlength="255" class="{{ $input }}">
                            @error('reference') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="{{ $label }}" for="proof_camera">Scanner avec la caméra</label>
                                <input id="proof_camera" name="proof_camera" type="file" accept="image/*" capture="environment" class="{{ $fileInput }}">
                                @error('proof_camera') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}" for="proof_file">Ou choisir un fichier (image, PDF)</label>
                                <input id="proof_file" name="proof_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="{{ $fileInput }}">
                                @error('proof_file') <p class="{{ $err }}">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <img id="proof-preview" alt="Aperçu de la preuve" class="hidden max-h-64 rounded-xl border border-slate-200">
                        <label class="flex items-start gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="proof_later" value="1" @checked(old('proof_later')) class="mt-0.5 h-4 w-4 rounded border-slate-300">
                            <span>Je n’ai pas encore ma preuve : je la déposerai plus tard depuis mon espace.</span>
                        </label>
                    @else
                        <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">Le paiement se règle auprès de l’équipe d’organisation.</p>
                    @endif
                </fieldset>
            @endif

            <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-tlink-blue/25 transition hover:bg-tlink-blue-700">Envoyer ma fiche</button>
        </form>

        @php
            $clubsJson = $clubs->map(fn ($list) => $list->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])->values());
            $oldJson = ['club' => (string) old('club_id'), 'position' => (string) old('position_id'), 'jclub' => (string) old('joined_club_id'), 'jposition' => (string) old('joined_position_id')];
        @endphp
        <script>
            (function () {
                var form = document.getElementById('completion-form');
                var clubsByScope = {!! json_encode($clubsJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
                var old = {!! json_encode($oldJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

                function reset(select, text, disabled) {
                    select.innerHTML = '';
                    var option = document.createElement('option');
                    option.value = '';
                    option.textContent = text;
                    select.appendChild(option);
                    select.disabled = disabled;
                }

                function loadPositions(position, target, preselect, emptyText) {
                    reset(position, 'Chargement…', true);
                    fetch(form.dataset.positionsUrl + '?target=' + encodeURIComponent(target), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (response) { if (!response.ok) { throw new Error('http'); } return response.json(); })
                        .then(function (data) {
                            reset(position, data.positions.length ? emptyText : 'Aucun poste', false);
                            data.positions.forEach(function (item) {
                                var option = document.createElement('option');
                                option.value = item.id;
                                option.textContent = item.title + (item.leader ? ' (responsabilité)' : '');
                                if (String(item.id) === String(preselect)) { option.selected = true; }
                                position.appendChild(option);
                            });
                        })
                        .catch(function () { reset(position, 'Impossible de charger les postes', true); });
                }

                // Partie → club (obligatoire quand la partie en a) → poste.
                function cascade(scope, club, position, preClub, prePosition) {
                    function onScope(clubPre, positionPre) {
                        if (!scope.value) { reset(club, 'Choisis d’abord la partie', true); reset(position, 'Choisis d’abord la partie', true); return; }
                        var clubs = clubsByScope[scope.value] || [];
                        if (!clubs.length) {
                            reset(club, 'Aucun club dans cette partie', true);
                            loadPositions(position, 'scope:' + scope.value, positionPre, 'Simple membre');
                            return;
                        }
                        reset(club, 'Choisir le club…', false);
                        clubs.forEach(function (item) {
                            var option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.title;
                            if (String(item.id) === String(clubPre)) { option.selected = true; }
                            club.appendChild(option);
                        });
                        reset(position, 'Choisis d’abord le club', true);
                        if (club.value) { loadPositions(position, 'club:' + club.value, positionPre, 'Simple membre'); }
                    }
                    scope.addEventListener('change', function () { onScope('', ''); });
                    club.addEventListener('change', function () {
                        if (club.value) { loadPositions(position, 'club:' + club.value, '', 'Simple membre'); } else { reset(position, 'Choisis d’abord le club', true); }
                    });
                    onScope(preClub, prePosition);
                }

                cascade(document.getElementById('cur-scope'), document.getElementById('cur-club'), document.getElementById('cur-position'), old.club, old.position);
                cascade(document.getElementById('join-scope'), document.getElementById('join-club'), document.getElementById('join-position'), old.jclub, old.jposition);

                var mandate = document.getElementById('joined_mandate_id');
                var joinFields = document.getElementById('join-fields');
                var joinHint = document.getElementById('join-hint');
                function toggleJoin() {
                    var on = !!mandate.value;
                    joinFields.classList.toggle('hidden', !on);
                    joinHint.classList.toggle('hidden', !on);
                    document.getElementById('join-scope').required = on;
                }
                mandate.addEventListener('change', toggleJoin);
                toggleJoin();

                function preview(input, img, placeholder) {
                    if (!input) { return; }
                    input.addEventListener('change', function () {
                        var file = input.files && input.files[0];
                        if (!file || file.type.indexOf('image/') !== 0) { return; }
                        img.src = URL.createObjectURL(file);
                        img.classList.remove('hidden');
                        if (placeholder) { placeholder.classList.add('hidden'); }
                    });
                }
                preview(document.getElementById('avatar'), document.getElementById('avatar-preview'), document.getElementById('avatar-placeholder'));
                var proofPreview = document.getElementById('proof-preview');
                if (proofPreview) {
                    preview(document.getElementById('proof_camera'), proofPreview);
                    preview(document.getElementById('proof_file'), proofPreview);
                }

                var method = document.getElementById('method');
                if (method) {
                    function showHelp() {
                        document.querySelectorAll('[data-method-help]').forEach(function (el) {
                            el.classList.toggle('hidden', el.dataset.methodHelp !== method.value);
                        });
                    }
                    method.addEventListener('change', showHelp);
                    showHelp();
                }
            })();
        </script>
    @endif
</div>
@endsection
