@extends('layouts.base', ['title' => 'Guides d’utilisation | T.Link'])

@section('content')
@php
    $input = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10';
@endphp
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    @include('partials.app-header')

    <main class="app-container space-y-6 py-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-semibold text-tlink-blue">Guides d’utilisation</h1>
                <p class="max-w-3xl text-sm text-slate-600">Apprenez à vous servir de T.Link pas à pas : chaque guide se lit en quelques minutes, en animation ou en notice écrite.</p>
            </div>
            @if($guides->isNotEmpty())
                <p class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200">
                    <i class="fa-solid fa-circle-check text-emerald-500" aria-hidden="true"></i>
                    {{ $completedCount }} guide{{ $completedCount > 1 ? 's' : '' }} terminé{{ $completedCount > 1 ? 's' : '' }} sur {{ $guides->count() }}
                </p>
            @endif
        </header>

        <form method="get" action="{{ route('guides.index') }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="relative min-w-[12rem] flex-1">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Rechercher un guide…" aria-label="Rechercher un guide" class="{{ $input }} pl-9">
            </div>
            @if($categories->count() > 1)
                <select name="categorie" aria-label="Catégorie" class="{{ $input }} w-auto">
                    <option value="">Toutes les catégories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['categorie'] ?? null) === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Rechercher</button>
            @if(array_filter($filters))
                <a href="{{ route('guides.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">Réinitialiser</a>
            @endif
        </form>

        @forelse($grouped as $category => $items)
            <section aria-label="{{ $category }}" class="space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $category }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($items as $guide)
                        @php
                            $accent = $guide->accent();
                            $done = $progress[$guide->id] ?? null;
                            $isDone = $done?->completed_at !== null;
                            $inProgress = ! $isDone && $done !== null && $done->last_step > 0;
                        @endphp
                        <a href="{{ route('guides.show', $guide->slug) }}" class="group flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-soft transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl text-lg {{ $accent['soft'] }}"><i class="{{ $guide->iconClass() }}" aria-hidden="true"></i></span>
                                @if($isDone)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Terminé</span>
                                @elseif($inProgress)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Reprendre · étape {{ $done->last_step + 1 }}</span>
                                @endif
                            </div>
                            <div class="flex-1">
                                <h3 class="font-display text-lg font-semibold leading-snug text-slate-900">{{ $guide->title }}</h3>
                                @if($guide->summary)
                                    <p class="mt-1 text-sm text-slate-600">{{ $guide->summary }}</p>
                                @endif
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $guide->steps_count }} étape{{ $guide->steps_count > 1 ? 's' : '' }} · environ {{ $guide->minutes() }} min</span>
                                <span class="font-semibold text-tlink-blue group-hover:underline">{{ $isDone ? 'Revoir' : ($inProgress ? 'Reprendre' : 'Commencer') }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                @if(array_filter($filters))
                    Aucun guide ne correspond à votre recherche.
                @else
                    Aucun guide n’est disponible pour le moment.
                @endif
            </p>
        @endforelse
    </main>
</div>
@endsection
