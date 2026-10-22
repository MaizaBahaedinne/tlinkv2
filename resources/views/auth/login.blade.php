@extends('auth.layout', [
    'title' => 'Connexion | T.Link',
    'heroTitle' => 'Content de te revoir sur T.Link.',
    'heroText' => 'Retrouve ta communauté, tes clubs et toutes les actualités de la Fondation Tunivisions.',
])

@section('form')
<div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-soft sm:p-10">
    <h1 class="font-display text-3xl font-semibold text-slate-900">Connexion</h1>
    <p class="mt-2 text-sm text-slate-500">Accède à l'espace social de la Fondation Tunivisions.</p>

    @include('auth.partials.social-buttons', ['socialVerb' => 'Se connecter'])

    <form class="space-y-5" method="post" action="{{ route('login.store') }}">
        @csrf
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="email">Email</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="toi@exemple.com"
                       class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-3 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10">
            </div>
            @error('email') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        @include('auth.partials.password-field')

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 accent-[#d00031]">
            Rester connecté
        </label>

        <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-tlink-blue/25 transition hover:bg-tlink-blue-700">Se connecter</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        Pas encore de compte ?
        <a class="font-semibold text-tlink-blue hover:underline" href="{{ route('register') }}">Créer un compte</a>
    </p>
</div>
@endsection
