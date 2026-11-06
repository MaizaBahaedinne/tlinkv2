{{-- En-tête de l'interface d'intégration : volontairement distinct de celui de l'application (pas de navigation, ni recherche, ni notifications). --}}
<header class="sticky top-0 z-20 border-b border-slate-200 bg-white">
    <div class="app-container flex max-w-3xl items-center justify-between gap-3 py-2.5">
        <a href="{{ route('integration.show') }}" class="flex items-center gap-3" aria-label="Mon intégration">
            <img src="{{ asset('tlink-logo.webp') }}" alt="T.Link" width="447" height="169" class="h-9 w-auto">
            <span class="hidden border-l border-slate-200 pl-3 text-sm font-semibold text-slate-500 sm:inline">Intégration</span>
        </a>
        <div class="flex items-center gap-4">
            @unless(request()->routeIs('integration.*'))
                <a href="{{ route('integration.show') }}" class="text-sm font-semibold text-tlink-blue hover:underline">Mes étapes</a>
            @endunless
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-slate-500 hover:text-slate-800">Se déconnecter</button>
            </form>
        </div>
    </div>
</header>
