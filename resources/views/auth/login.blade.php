@extends('layouts.base', ['title' => 'Connexion | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand px-4 py-10">
    <div class="mx-auto w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-soft">
        <h1 class="font-display text-2xl font-semibold text-tlink-blue">Connexion T.Link</h1>
        <p class="mt-2 text-sm text-slate-500">Accede a l'espace social Fondation Tunivisions.</p>

        <form class="mt-6 space-y-4" method="post" action="{{ route('login.store') }}">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="password">Mot de passe</label>
                <input id="password" name="password" type="password" required class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue">
            </div>
            <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Se connecter</button>
        </form>

        @include('auth.partials.social-buttons', ['socialVerb' => 'Se connecter'])

        <p class="mt-4 text-center text-sm text-slate-600">
            Pas encore de compte ?
            <a class="font-medium text-tlink-blue hover:underline" href="{{ route('register') }}">Inscription</a>
        </p>
    </div>
</div>
@endsection
