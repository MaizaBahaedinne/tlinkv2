{{-- En-tête des applications cloisonnées (Store, Academy, Messenger) : uniquement leur logo, leurs propres pages et le compte.
     Aucun lien vers T.Link ni vers une autre application. --}}
@php
    $app = collect(config('mobile.apps'))->firstWhere('section', $section);
    $appName = $app['name'] ?? 'Tunivisions';
    $logo = asset('app-icons/'.($app['icons'] ?? 'link').'/icon-192.png');
    $homeUrl = \App\Support\Domains::homeUrl(request());

    $sectionNav = match ($section) {
        'store' => [
            ['label' => 'Boutique', 'href' => route('store.index'), 'icon' => 'fa-solid fa-bag-shopping', 'active' => request()->routeIs('store.index', 'store.products.*', 'store.packs.*')],
            ['label' => 'Panier', 'href' => route('store.cart.index'), 'icon' => 'fa-solid fa-cart-shopping', 'active' => request()->routeIs('store.cart.*', 'store.checkout'), 'badge' => (int) ($storeCartCount ?? 0)],
            ['label' => 'Commandes', 'href' => route('store.orders.index'), 'icon' => 'fa-solid fa-box', 'active' => request()->routeIs('store.orders.*')],
        ],
        'academy' => [
            ['label' => 'Academy', 'href' => route('academy.index'), 'icon' => 'fa-solid fa-graduation-cap', 'active' => request()->routeIs('academy.*') && ! request()->routeIs('academy.calendar')],
            ['label' => 'Calendrier', 'href' => route('academy.calendar'), 'icon' => 'fa-regular fa-calendar', 'active' => request()->routeIs('academy.calendar')],
        ],
        default => [],
    };
    $meName = $me->name;
    $meAvatar = $me->profile?->photoUrl();
    $meInitials = mb_strtoupper(collect(preg_split('/\s+/u', trim($meName)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
@endphp
<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 lg:backdrop-blur">
    <div class="app-container flex items-center gap-x-3 py-2.5">
        <a href="{{ $homeUrl }}" class="flex shrink-0 items-center gap-2" aria-label="{{ $appName }}, accueil">
            <img src="{{ $logo }}" alt="" width="36" height="36" class="h-9 w-9 rounded-xl">
            <span class="hidden text-sm font-semibold text-slate-900 sm:inline">{{ $appName }}</span>
        </a>

        @if($sectionNav)
            <nav class="app-bottom-nav fixed inset-x-0 bottom-0 z-30 flex border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_12px_rgba(15,23,42,0.06)] lg:static lg:z-auto lg:ml-4 lg:w-auto lg:gap-2 lg:border-0 lg:bg-transparent lg:pb-0 lg:shadow-none" aria-label="Navigation de {{ $appName }}">
                @foreach($sectionNav as $item)
                    <a href="{{ $item['href'] }}" title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}" @if($item['active']) aria-current="page" @endif
                       class="relative flex h-14 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 text-base transition lg:h-10 lg:w-auto lg:flex-none lg:flex-row lg:gap-2 lg:rounded-xl lg:px-3 {{ $item['active'] ? 'text-tlink-blue lg:bg-rose-50' : 'text-slate-500 hover:text-slate-900 lg:hover:bg-slate-100' }}">
                        <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                        @if(($item['badge'] ?? 0) > 0)
                            <span class="absolute right-1/4 top-1.5 inline-flex min-w-[1.05rem] items-center justify-center rounded-full bg-tlink-blue px-1 text-[10px] font-bold leading-[1.05rem] text-white lg:static" aria-label="{{ $item['badge'] }} article(s) dans le panier">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                        @endif
                        <span class="max-w-full truncate px-1 text-[10px] font-medium leading-none lg:px-0 lg:text-sm">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="ml-auto flex items-center justify-end gap-1.5">
            @include('partials.install-app')
            <details class="relative" id="nav-user">
                <summary class="grid h-10 w-10 cursor-pointer list-none place-items-center overflow-hidden rounded-full bg-tlink-blue text-sm font-semibold text-white ring-2 ring-white transition hover:ring-rose-100 [&::-webkit-details-marker]:hidden" title="{{ $meName }}" aria-label="Menu de {{ $meName }}">
                    @if($meAvatar)
                        <img src="{{ $meAvatar }}" alt="" class="h-full w-full object-cover">
                    @else
                        {{ $meInitials }}
                    @endif
                </summary>
                <div class="absolute right-0 z-30 mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-2 shadow-soft">
                    <div class="border-b border-slate-100 px-3 pb-2.5 pt-1.5">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $meName }}</p>
                        @unless(str_ends_with((string) $me->email, '@legacy.invalid'))
                            <p class="truncate text-xs text-slate-500">{{ $me->email }}</p>
                        @endunless
                    </div>
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit" class="mt-1 block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">
                            <i class="fa-solid fa-right-from-bracket mr-2 w-4 text-center"></i>Déconnexion
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
