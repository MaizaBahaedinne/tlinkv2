@extends('auth.layout', [
    'title' => 'Inscription | T.Link',
    'heroTitle' => 'Rejoins la communauté Tunivisions.',
    'heroText' => 'Crée ton compte gratuitement pour publier, échanger et participer à la vie de la Fondation.',
])

@section('form')
<div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-soft sm:p-10">
    <h1 class="font-display text-3xl font-semibold text-slate-900">Créer un compte</h1>
    <p class="mt-2 text-sm text-slate-500">Rejoins la communauté Fondation Tunivisions.</p>

    @include('auth.partials.social-buttons', ['socialVerb' => 'S\'inscrire'])

    <form class="space-y-5" method="post" action="{{ route('register.store') }}">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="first_name">Prénom</label>
                <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required maxlength="120" autocomplete="given-name"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10">
                @error('first_name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="last_name">Nom de famille</label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required maxlength="120" autocomplete="family-name"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10">
                @error('last_name') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="email">Email</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="toi@exemple.com"
                       class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-3 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10">
            </div>
            @error('email') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        @include('auth.partials.password-field', ['autocomplete' => 'new-password'])
        @include('auth.partials.password-field', ['id' => 'password_confirmation', 'label' => 'Confirmation', 'autocomplete' => 'new-password', 'toggle' => false])

        <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-tlink-blue/25 transition hover:bg-tlink-blue-700">Créer mon compte</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        Déjà inscrit ?
        <a class="font-semibold text-tlink-blue hover:underline" href="{{ route('login') }}">Connexion</a>
    </p>
</div>
@endsection
