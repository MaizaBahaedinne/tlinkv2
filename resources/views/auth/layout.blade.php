@extends('layouts.base')

@section('content')
<div class="min-h-screen lg:grid lg:grid-cols-[1.1fr_1fr]">
    {{-- Hero --}}
    <aside class="relative isolate overflow-hidden bg-gradient-to-br from-[#6e0019] via-[#a30027] to-[#d00031] px-6 py-10 text-white sm:px-10 lg:flex lg:min-h-screen lg:flex-col lg:justify-between lg:px-14 lg:py-14">
        {{-- Motif en facettes derriere le contenu --}}
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-[#f5a623]/25 blur-3xl"></div>
            <div class="absolute right-10 top-1/4 h-40 w-40 rotate-45 rounded-3xl border border-white/20 bg-white/5"></div>
            <div class="absolute bottom-1/4 left-1/3 h-24 w-24 rotate-12 rounded-2xl border border-white/20 bg-white/5"></div>
            <div class="absolute -right-10 bottom-10 h-56 w-56 -rotate-12 rounded-[2.5rem] border border-white/15 bg-gradient-to-br from-white/15 to-transparent"></div>
            <svg class="absolute inset-0 h-full w-full opacity-[0.12]" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="auth-grid" width="36" height="36" patternUnits="userSpaceOnUse">
                        <path d="M36 0H0V36" fill="none" stroke="white" stroke-width="0.6"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#auth-grid)"/>
            </svg>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white font-display text-lg font-bold text-tlink-blue shadow-lg">T</span>
                <span class="font-display text-xl font-semibold tracking-tight">T.Link</span>
            </a>
            <a href="{{ url('/') }}" class="text-sm font-medium text-white/80 transition hover:text-white">&larr; Site de la Fondation</a>
        </div>

        <div class="mt-10 max-w-xl lg:mt-0">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider backdrop-blur">
                <span class="h-1.5 w-1.5 rounded-full bg-[#f5a623]"></span>
                Fondation Tunivisions
            </span>
            <h2 class="mt-5 font-display text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                {{ $heroTitle ?? 'Le réseau des talents et des clubs de la Fondation.' }}
            </h2>
            <p class="mt-4 text-base text-white/80 lg:text-lg">
                {{ $heroText ?? 'Échange avec ta communauté, suis les actualités et participe aux événements, au même endroit.' }}
            </p>

            <ul class="mt-8 hidden gap-3 sm:grid">
                @foreach ([
                    ['M3 5h18M3 12h18M3 19h12', 'Un fil d\'actualités pour toute la communauté'],
                    ['M8 7V3m8 4V3M4 11h16M5 21h14a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1z', 'Événements et inscriptions en quelques clics'],
                    ['M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m10-5.13a4 4 0 1 1-8 0 4 4 0 0 1 8 0z', 'Clubs, universités et lycées partout en Tunisie'],
                ] as [$icon, $label])
                    <li class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-medium backdrop-blur">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/15">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $icon }}"/></svg>
                        </span>
                        {{ $label }}
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="mt-10 hidden text-xs text-white/60 lg:block">&copy; {{ date('Y') }} Fondation Tunivisions</p>
    </aside>

    {{-- Formulaire --}}
    <main class="flex items-center justify-center px-4 py-10 sm:px-8 lg:min-h-screen">
        <div class="w-full {{ $formWidth ?? 'max-w-md' }}">
            @yield('form')
        </div>
    </main>
</div>

<script>
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.togglePassword);
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.querySelector('[data-eye-off]').classList.toggle('hidden', !show);
            button.querySelector('[data-eye]').classList.toggle('hidden', show);
        });
    });
</script>
@endsection
