@extends('layouts.base', ['title' => 'Clubs | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Les clubs</h1>
            <p class="max-w-2xl text-sm text-slate-600">Découvrez les clubs du réseau, leur bureau, leurs activités et leurs actualités.</p>
        </div>

        <form method="get" action="{{ route('club.index') }}" class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-soft">
            <input type="search" name="q" value="{{ $search }}" placeholder="Rechercher un club" aria-label="Rechercher un club" class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-tlink-blue focus:outline-none">
            <select name="scope" aria-label="Périmètre" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Tous les périmètres</option>
                @foreach($scopes as $slug => $label)
                    <option value="{{ $slug }}" @selected($scope === $slug)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Filtrer</button>
        </form>

        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($clubs as $club)
                <li>
                    <a href="{{ route('club.show', $club) }}" class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft transition hover:border-tlink-blue/40">
                        <div class="h-24 bg-gradient-to-br from-tlink-blue via-sky-700 to-rose-500">
                            @if($club->coverUrl())
                                <img src="{{ $club->coverUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="flex flex-1 items-start gap-3 px-4 pb-4">
                            <x-org-logo :url="$club->logoUrl()" :name="$club->title" size="sm" class="-mt-7 !h-14 !w-14 !rounded-xl !border-2 !border-white text-lg shadow-soft" />
                            <div class="min-w-0 pt-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $club->title }}</p>
                                <p class="truncate text-xs text-slate-500">{{ collect([$club->governorate?->name, $club->founded_on ? 'depuis '.$club->founded_on->year : null])->filter()->implode(' · ') }}</p>
                            </div>
                        </div>
                    </a>
                </li>
            @empty
                <li class="rounded-3xl border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500 shadow-soft sm:col-span-2 lg:col-span-3">Aucun club ne correspond à votre recherche.</li>
            @endforelse
        </ul>

        {{ $clubs->links() }}
    </main>
</div>
@endsection
