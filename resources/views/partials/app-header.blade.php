{{-- En-tête commun des pages T.Link (hors admin) : même gabarit que le fil, contenu à 80 % de la largeur. --}}
<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="app-container flex items-center justify-between py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-2xl bg-tlink-blue p-2 text-white shadow-soft">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M4 5.5A2.5 2.5 0 016.5 3h11A2.5 2.5 0 0120 5.5v13a2.5 2.5 0 01-2.5 2.5h-11A2.5 2.5 0 014 18.5v-13zm3.75 1.25a1 1 0 100 2h8.5a1 1 0 100-2h-8.5zm0 4a1 1 0 100 2h8.5a1 1 0 100-2h-8.5zm0 4a1 1 0 100 2h5.25a1 1 0 100-2H7.75z"/>
                </svg>
            </div>
            <div>
                <p class="font-display text-xl font-semibold text-tlink-blue">T.Link</p>
                <p class="text-xs text-slate-500">Fondation Tunivisions</p>
            </div>
        </a>
        <div class="flex items-center gap-3">
            <a href="{{ route('home') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Accueil</a>
            <details class="relative">
                <summary class="list-none cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">
                    <span class="inline-flex items-center gap-2">
                        <i class="fa-regular fa-user"></i>
                        <span>{{ auth()->user()->name }}</span>
                        <i class="fa-solid fa-chevron-down text-xs text-slate-500"></i>
                    </span>
                </summary>
                <div class="absolute right-0 z-30 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-2 shadow-soft">
                    <a href="{{ route('profile.show') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-regular fa-id-card mr-2"></i>Profil</a>
                    @if($canAdmin ?? false)
                        <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-solid fa-list-check mr-2"></i>Administration</a>
                    @endif
                    <a href="{{ route('orgchart.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-solid fa-sitemap mr-2"></i>Organigramme</a>
                    @if($hasOffice ?? false)
                        <a href="{{ route('office.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-solid fa-briefcase mr-2"></i>Mon bureau</a>
                    @endif
                    <a href="{{ route('notifications.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-regular fa-bell mr-2"></i>Notifications</a>
                    <form action="{{ route('logout') }}" method="post" class="mt-1">
                        @csrf
                        <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">
                            <i class="fa-solid fa-right-from-bracket mr-2"></i>Deconnexion
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
