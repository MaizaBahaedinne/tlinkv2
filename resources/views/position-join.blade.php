@extends('auth.layout', [
    'title' => 'Candidature : '.$node->title.' | T.Link',
    'heroTitle' => 'Rejoins l’équipe : '.$node->title.'.',
    'heroText' => 'Crée ton compte complet en une seule fois, avec ta photo et tes informations. Ta candidature sera examinée par l’équipe de la Fondation.',
    'formWidth' => 'max-w-2xl',
])

@section('form')
@php
    $input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-700';
    $legend = 'text-xs font-bold uppercase tracking-wider text-tlink-blue';
@endphp
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft sm:p-10">
    <p class="{{ $legend }}">Candidature</p>
    <h1 class="mt-1 font-display text-3xl font-semibold text-slate-900">{{ $node->title }}</h1>
    @if ($node->subtitle)
        <p class="mt-1 text-sm text-slate-500">{{ $node->subtitle }}</p>
    @endif

    @if (session('status'))
        <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    @if ($closed)
        <p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Ce poste est déjà pourvu : les candidatures sont fermées.</p>
    @elseif ($alreadyHolder)
        <p class="mt-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">Vous occupez déjà ce poste.</p>
    @elseif ($existing && $existing->status === 'pending')
        <p class="mt-6 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">Votre candidature à ce poste est en cours d’examen. Vous serez informé(e) de la décision.</p>
    @elseif (auth()->check())
        <p class="mt-4 text-sm text-slate-600">Vous êtes connecté(e) en tant que <strong>{{ auth()->user()->name }}</strong>. Votre profil actuel sera joint à la candidature.</p>

        <form class="mt-6 space-y-5" method="post" action="{{ route('position.join.store', $node->join_token) }}">
            @csrf
            <div>
                <label class="{{ $label }}" for="message">Pourquoi ce poste ?</label>
                <textarea id="message" name="message" rows="5" required maxlength="2000" class="{{ $input }}">{{ old('message') }}</textarea>
                @error('message') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-tlink-blue/25 transition hover:bg-tlink-blue-700">Envoyer ma candidature</button>
        </form>
    @else
        <p class="mt-4 text-sm text-slate-600">Remplissez ce formulaire : il crée votre compte et dépose votre candidature. Les champs marqués d’une étoile sont obligatoires.</p>

        <form class="mt-6 space-y-8" method="post" action="{{ route('position.join.store', $node->join_token) }}" enctype="multipart/form-data">
            @csrf

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Photo de profil *</legend>
                <div class="flex items-center gap-4">
                    <img id="avatar-preview" alt="" class="hidden h-20 w-20 rounded-full border border-slate-200 object-cover">
                    <span id="avatar-placeholder" class="grid h-20 w-20 place-items-center rounded-full border border-dashed border-slate-300 bg-slate-50 text-slate-400">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-tlink-blue file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-tlink-blue-700">
                        <p class="mt-1 text-xs text-slate-500">JPG, PNG ou WebP, 5 Mo maximum. Une photo où l’on voit bien votre visage.</p>
                        @error('avatar') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Identité</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}" for="first_name">Prénom *</label>
                        <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required maxlength="120" autocomplete="given-name" class="{{ $input }}">
                        @error('first_name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="last_name">Nom de famille *</label>
                        <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required maxlength="120" autocomplete="family-name" class="{{ $input }}">
                        @error('last_name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="gender">Genre *</label>
                        <select id="gender" name="gender" required class="{{ $input }}">
                            <option value="">Choisir…</option>
                            <option value="female" @selected(old('gender') === 'female')>Femme</option>
                            <option value="male" @selected(old('gender') === 'male')>Homme</option>
                        </select>
                        @error('gender') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="birth_date">Date de naissance *</label>
                        <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}" required max="{{ now()->toDateString() }}" autocomplete="bday" class="{{ $input }}">
                        @error('birth_date') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Coordonnées</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $label }}" for="phone">Téléphone *</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" class="{{ $input }}">
                        @error('phone') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="phone_secondary">Téléphone secondaire</label>
                        <input id="phone_secondary" name="phone_secondary" type="tel" value="{{ old('phone_secondary') }}" maxlength="20" class="{{ $input }}">
                        @error('phone_secondary') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="governorate_id">Gouvernorat *</label>
                        <select id="governorate_id" name="governorate_id" required class="{{ $input }}">
                            <option value="">Choisir…</option>
                            @foreach ($governorates as $governorate)
                                <option value="{{ $governorate->id }}" @selected((string) old('governorate_id') === (string) $governorate->id)>{{ $governorate->name }}</option>
                            @endforeach
                        </select>
                        @error('governorate_id') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="delegation">Délégation</label>
                        <input id="delegation" name="delegation" type="text" value="{{ old('delegation') }}" maxlength="120" class="{{ $input }}">
                        @error('delegation') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="{{ $label }}" for="address">Adresse</label>
                    <input id="address" name="address" type="text" value="{{ old('address') }}" maxlength="255" autocomplete="street-address" class="{{ $input }}">
                    @error('address') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Présentation</legend>
                <div>
                    <label class="{{ $label }}" for="headline">Titre / fonction actuelle</label>
                    <input id="headline" name="headline" type="text" value="{{ old('headline') }}" maxlength="160" placeholder="Ex. Étudiant en ingénierie, entrepreneur…" class="{{ $input }}">
                    @error('headline') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $label }}" for="bio">À propos de vous</label>
                    <textarea id="bio" name="bio" rows="3" maxlength="2000" class="{{ $input }}">{{ old('bio') }}</textarea>
                    @error('bio') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="{{ $label }}" for="facebook_url">Facebook</label>
                        <input id="facebook_url" name="facebook_url" type="url" value="{{ old('facebook_url') }}" maxlength="255" placeholder="https://" class="{{ $input }}">
                        @error('facebook_url') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="instagram_url">Instagram</label>
                        <input id="instagram_url" name="instagram_url" type="url" value="{{ old('instagram_url') }}" maxlength="255" placeholder="https://" class="{{ $input }}">
                        @error('instagram_url') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}" for="linkedin_url">LinkedIn</label>
                        <input id="linkedin_url" name="linkedin_url" type="url" value="{{ old('linkedin_url') }}" maxlength="255" placeholder="https://" class="{{ $input }}">
                        @error('linkedin_url') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Compte</legend>
                <div>
                    <label class="{{ $label }}" for="email">Email *</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="toi@exemple.com" class="{{ $input }}">
                    @error('email') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('auth.partials.password-field', ['autocomplete' => 'new-password', 'label' => 'Mot de passe *'])
                    @include('auth.partials.password-field', ['id' => 'password_confirmation', 'label' => 'Confirmation *', 'autocomplete' => 'new-password', 'toggle' => false])
                </div>
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="{{ $legend }}">Votre candidature</legend>
                <div>
                    <label class="{{ $label }}" for="message">Pourquoi ce poste ? *</label>
                    <textarea id="message" name="message" rows="4" required maxlength="2000" class="{{ $input }}">{{ old('message') }}</textarea>
                    @error('message') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-tlink-blue/25 transition hover:bg-tlink-blue-700">Créer mon compte et candidater</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-600">
            Déjà un compte ?
            <a class="font-semibold text-tlink-blue hover:underline" href="{{ route('login') }}">Connectez-vous</a>
            pour candidater avec votre profil.
        </p>

        <script>
            (function () {
                var input = document.getElementById('avatar');
                var preview = document.getElementById('avatar-preview');
                var placeholder = document.getElementById('avatar-placeholder');
                if (!input) { return; }
                input.addEventListener('change', function () {
                    var file = input.files && input.files[0];
                    if (!file) { return; }
                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                });
            })();
        </script>
    @endif
</div>
@endsection
