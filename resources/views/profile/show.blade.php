@extends('layouts.base', ['title' => 'Mon profil | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand px-4 py-10">
    <div class="mx-auto w-full max-w-3xl rounded-3xl border border-slate-200 bg-white p-8 shadow-soft">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mon profil</h1>
            <a href="{{ route('home') }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Retour accueil</a>
        </div>

        <form method="post" action="{{ route('profile.update') }}" class="grid gap-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="headline">Titre</label>
                <input id="headline" name="headline" type="text" value="{{ old('headline', $profile?->headline) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="bio">Biographie</label>
                <textarea id="bio" name="bio" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2">{{ old('bio', $profile?->bio) }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="skills">Competences</label>
                <input id="skills" name="skills" type="text" value="{{ old('skills', $profile?->skills) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="languages">Langues</label>
                <input id="languages" name="languages" type="text" value="{{ old('languages', $profile?->languages) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2">
            </div>

            <button type="submit" class="mt-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:bg-tlink-blue-700">Enregistrer</button>
        </form>
    </div>
</div>
@endsection
