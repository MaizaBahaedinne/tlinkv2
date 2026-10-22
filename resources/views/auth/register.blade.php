@extends('layouts.base', ['title' => 'Inscription | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand px-4 py-10">
    <div class="mx-auto w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-soft">
        <h1 class="font-display text-2xl font-semibold text-tlink-blue">Inscription T.Link</h1>
        <p class="mt-2 text-sm text-slate-500">Rejoins la communaute Fondation Tunivisions.</p>

        <form class="mt-6 space-y-4" method="post" action="{{ route('register.store') }}">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="first_name">Prénom</label>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required maxlength="120" autocomplete="given-name" class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue">
                    @error('first_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="last_name">Nom de famille</label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required maxlength="120" autocomplete="family-name" class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue">
                    @error('last_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="password">Mot de passe</label>
                <input id="password" name="password" type="password" required class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="password_confirmation">Confirmation</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-xl border border-slate-300 px-3 py-2 outline-none focus:border-tlink-blue">
            </div>
            <button type="submit" class="w-full rounded-xl bg-tlink-cyan px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Creer un compte</button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-600">
            Deja inscrit ?
            <a class="font-medium text-tlink-blue hover:underline" href="{{ route('login') }}">Connexion</a>
        </p>
    </div>
</div>
@endsection
