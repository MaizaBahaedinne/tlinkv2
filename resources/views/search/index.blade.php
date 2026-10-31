@extends('layouts.base', ['title' => 'Recherche | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-6 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Recherche</h1>
            @if(mb_strlen($term) >= 2)
                <p class="text-sm text-slate-600">Résultats pour « {{ $term }} »</p>
            @else
                <p class="text-sm text-slate-600">Saisissez au moins 2 caractères dans la barre de recherche.</p>
            @endif
        </div>

        @if(mb_strlen($term) >= 2 && $groups === [])
            <div class="card p-8 text-center text-sm text-slate-500">Aucun résultat pour « {{ $term }} ». Essayez un autre nom, un club ou un gouvernorat.</div>
        @endif

        @foreach($groups as $group)
            <section class="card overflow-hidden" aria-labelledby="search-{{ $group['key'] }}">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h2 id="search-{{ $group['key'] }}" class="font-display text-lg font-semibold text-slate-900">{{ $group['label'] }} <span class="text-sm font-normal text-slate-400">({{ count($group['items']) }})</span></h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach($group['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="flex items-center gap-4 px-5 py-3 hover:bg-slate-50">
                                @if($item['image'])
                                    <img src="{{ $item['image'] }}" alt="" loading="lazy" class="h-11 w-11 shrink-0 rounded-full object-cover">
                                @else
                                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-rose-50 text-sm font-bold text-tlink-blue">{{ mb_strtoupper(mb_substr($item['title'], 0, 1)) }}</span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-slate-900">{{ $item['title'] }} <x-verified-badge :verified="$item['verified'] ?? false" /></span>
                                    @if($item['subtitle'])
                                        <span class="block truncate text-sm text-slate-500">{{ $item['subtitle'] }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </main>
</div>
@endsection
