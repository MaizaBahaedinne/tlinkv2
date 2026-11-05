@extends('layouts.base')

@section('content')
@php
    $others = [
        'store' => ['label' => 'Stores', 'url' => route('store.showcase')],
        'academy' => ['label' => 'Academy', 'url' => route('academy.showcase')],
    ];
@endphp
<div class="min-h-screen bg-tlink-sand text-slate-800">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ $siteUrl }}" class="flex items-center gap-3" aria-label="Fondation Tunivisions">
                <img src="{{ asset('logoFondation.webp') }}" alt="Fondation Tunivisions" class="h-10 w-auto">
            </a>
            <nav class="flex flex-wrap items-center gap-1 text-sm font-semibold text-slate-600" aria-label="Navigation">
                <a href="{{ $siteUrl }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">La Fondation</a>
                <a href="{{ $appUrl }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">T.Link</a>
                @foreach($others as $key => $other)
                    <a href="{{ $other['url'] }}" class="rounded-lg px-3 py-2 hover:bg-slate-100 {{ $service === $key ? 'bg-slate-100 text-slate-900' : '' }}" @if($service === $key) aria-current="page" @endif>{{ $other['label'] }}</a>
                @endforeach
                @auth
                    <a href="{{ $service === 'store' ? route('store.index') : route('academy.index') }}" class="ml-2 rounded-lg bg-[#a30027] px-4 py-2 text-white hover:bg-[#8a0021]">Mon espace</a>
                @else
                    <a href="{{ $loginUrl }}" class="ml-2 rounded-lg border border-slate-300 px-4 py-2 text-slate-700 hover:bg-slate-50">Se connecter</a>
                @endauth
            </nav>
        </div>
    </header>

    @yield('showcase')

    <footer class="mt-16 bg-slate-900 text-slate-300">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm">
            <p>© {{ now()->year }} Fondation Tunivisions. Tous droits réservés.</p>
            <p class="flex flex-wrap gap-4">
                <a href="{{ $siteUrl }}" class="hover:text-white">Site de la Fondation</a>
                <a href="{{ $appUrl }}" class="hover:text-white">T.Link</a>
                <a href="{{ $registerUrl }}" class="hover:text-white">Créer un compte</a>
            </p>
        </div>
    </footer>
</div>
@endsection
