{{-- En-tête commun des pages T.Link (hors admin) : logo, navigation par icônes, recherche, notifications, avatar. --}}
@php
    $me = auth()->user();
    $avatar = $me->profile?->photoUrl();
    $initials = mb_strtoupper(collect(preg_split('/\s+/u', trim($me->name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    $unread = (int) ($unreadNotifications ?? 0);

    $navItems = array_values(array_filter([
        ['label' => 'Fil', 'href' => route('home'), 'icon' => 'fa-solid fa-house', 'active' => request()->routeIs('home')],
        ['label' => 'Academy', 'href' => route('academy.index'), 'icon' => 'fa-solid fa-graduation-cap', 'active' => request()->routeIs('academy.*')],
        ($hasOffice ?? false) ? ['label' => 'Mon bureau', 'href' => route('office.index'), 'icon' => 'fa-solid fa-briefcase', 'active' => request()->routeIs('office.*')] : null,
        ($canAdmin ?? false) ? ['label' => 'Administration', 'href' => route('admin.dashboard'), 'icon' => 'fa-solid fa-list-check', 'active' => false] : null,
    ]));
@endphp
<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 lg:backdrop-blur">
    <div class="app-container flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5">
        <a href="{{ route('home') }}" class="flex shrink-0 items-center lg:flex-1 lg:basis-0" aria-label="T.Link, accueil">
            <img src="{{ asset('tlink-logo.webp') }}" alt="T.Link" width="447" height="169" class="h-9 w-auto">
        </a>

        {{-- Mobile et tablette : barre d'onglets fixée en pied de page ; ordinateur : icônes centrées dans l'en-tête. --}}
        <nav class="app-bottom-nav fixed inset-x-0 bottom-0 z-30 flex border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_12px_rgba(15,23,42,0.06)] lg:static lg:z-auto lg:w-auto lg:gap-2 lg:border-0 lg:bg-transparent lg:pb-0 lg:shadow-none" aria-label="Navigation de l'application">
            @foreach($navItems as $item)
                <a href="{{ $item['href'] }}" title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}" @if($item['active']) aria-current="page" @endif
                   class="flex h-14 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 text-base transition lg:h-10 lg:w-10 lg:flex-none lg:rounded-xl {{ $item['active'] ? 'text-tlink-blue lg:bg-rose-50' : 'text-slate-500 hover:text-slate-900 lg:hover:bg-slate-100' }}">
                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span class="max-w-full truncate px-1 text-[10px] font-medium leading-none lg:hidden">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex min-w-0 flex-1 items-center justify-end gap-1.5 lg:basis-0">
            <form id="nav-search" action="{{ route('search.index') }}" method="get" role="search" data-suggest-url="{{ route('search.suggest') }}"
                  class="relative min-w-0 max-w-40 flex-1 transition-[max-width] duration-200 focus-within:max-w-60">
                <label for="nav-search-input" class="sr-only">Rechercher sur T.Link</label>
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
                <input id="nav-search-input" type="search" name="q" value="{{ request()->routeIs('search.index') ? request('q') : '' }}" maxlength="80" autocomplete="off"
                       placeholder="Rechercher…"
                       role="combobox" aria-expanded="false" aria-controls="nav-search-results" aria-autocomplete="list"
                       class="w-full rounded-xl border border-transparent bg-slate-100 py-1.5 pl-8 pr-2 text-sm outline-none transition focus:border-tlink-blue focus:bg-white">
                <div id="nav-search-results" role="listbox" hidden class="absolute right-0 top-full z-40 mt-2 max-h-[70vh] w-80 max-w-[90vw] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-soft"></div>
            </form>

            @if($messengerEnabled ?? false)
                @php $unreadMessages = (int) ($unreadMessages ?? 0); @endphp
                <a href="{{ route('messenger.index') }}" title="Messages" aria-label="Messages" @if(request()->routeIs('messenger.*')) aria-current="page" @endif id="nav-messages" data-unread-url="{{ route('messenger.unread') }}"
                   class="relative grid h-10 w-10 place-items-center rounded-xl text-base transition {{ request()->routeIs('messenger.*') ? 'bg-rose-50 text-tlink-blue' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' }}">
                    <i class="fa-regular fa-comments" aria-hidden="true"></i>
                    <span id="nav-messages-badge" @if($unreadMessages < 1) hidden @endif class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.15rem] items-center justify-center rounded-full bg-tlink-blue px-1 text-[10px] font-bold leading-[1.15rem] text-white ring-2 ring-white">{{ $unreadMessages > 99 ? '99+' : $unreadMessages }}</span>
                </a>
            @endif

            <a href="{{ route('notifications.index') }}" title="Notifications" aria-label="Notifications{{ $unread > 0 ? ' ('.$unread.' non lues)' : '' }}" @if(request()->routeIs('notifications.*')) aria-current="page" @endif
               class="relative grid h-10 w-10 place-items-center rounded-xl text-base transition {{ request()->routeIs('notifications.*') ? 'bg-rose-50 text-tlink-blue' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' }}">
                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                @if($unread > 0)
                    <span class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.15rem] items-center justify-center rounded-full bg-tlink-blue px-1 text-[10px] font-bold leading-[1.15rem] text-white ring-2 ring-white" aria-label="{{ $unread }} non lues">{{ $unread > 99 ? '99+' : $unread }}</span>
                @endif
            </a>

            <details class="relative" id="nav-user">
                <summary class="grid h-10 w-10 cursor-pointer list-none place-items-center overflow-hidden rounded-full bg-tlink-blue text-sm font-semibold text-white ring-2 ring-white transition hover:ring-rose-100 [&::-webkit-details-marker]:hidden" title="{{ $me->name }}" aria-label="Menu de {{ $me->name }}">
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="" class="h-full w-full object-cover">
                    @else
                        {{ $initials }}
                    @endif
                </summary>
                <div class="absolute right-0 z-30 mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-2 shadow-soft">
                    <div class="border-b border-slate-100 px-3 pb-2.5 pt-1.5">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $me->name }}</p>
                        @unless(str_ends_with((string) $me->email, '@legacy.invalid'))
                            <p class="truncate text-xs text-slate-500">{{ $me->email }}</p>
                        @endunless
                    </div>
                    <a href="{{ route('profile.show') }}" class="mt-1 block rounded-lg px-3 py-2 text-sm hover:bg-slate-50"><i class="fa-regular fa-id-card mr-2 w-4 text-center text-slate-500"></i>Mon profil</a>
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50">
                            <i class="fa-solid fa-right-from-bracket mr-2 w-4 text-center"></i>Déconnexion
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>

@if(($messengerEnabled ?? false) && ! request()->routeIs('messenger.*'))
    @include('messenger.dock')
@endif

<script>
    // Pastille des messages non lus : mise à jour par T.Link Messenger, ou toutes les 30 s ailleurs dans l'application.
    (function () {
        var link = document.getElementById('nav-messages');
        if (!link) { return; }
        var badge = document.getElementById('nav-messages-badge');

        window.tlinkMessengerBadge = function (count) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count < 1;
        };

        if (document.getElementById('messenger')) { return; }

        function refresh() {
            if (document.hidden || document.getElementById('mg-dock')) { return; }
            fetch(link.getAttribute('data-unread-url'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) { if (data) { window.tlinkMessengerBadge(data.unread || 0); } })
                .catch(function () { /* badge conservé */ });
        }

        setInterval(refresh, 30000);
        document.addEventListener('visibilitychange', refresh);
    })();
</script>

<script>
    // Le menu de l'avatar se referme au clic ailleurs ou sur Échap.
    (function () {
        var menu = document.getElementById('nav-user');
        if (!menu) { return; }
        document.addEventListener('click', function (event) { if (!menu.contains(event.target)) { menu.open = false; } });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { menu.open = false; } });
    })();
</script>

<script>
    (function () {
        var form = document.getElementById('nav-search');
        if (!form) { return; }

        var input = document.getElementById('nav-search-input');
        var box = document.getElementById('nav-search-results');
        var endpoint = form.getAttribute('data-suggest-url');
        var timer = null;
        var pending = null;
        var links = [];
        var active = -1;

        function el(tag, className, text) {
            var node = document.createElement(tag);
            if (className) { node.className = className; }
            if (text) { node.textContent = text; }
            return node;
        }

        function open() { box.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        function close() { box.hidden = true; input.setAttribute('aria-expanded', 'false'); setActive(-1); }

        function setActive(index) {
            links.forEach(function (link, i) {
                link.classList.toggle('bg-slate-100', i === index);
                link.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });
            active = index;
            if (links[index]) { links[index].scrollIntoView({ block: 'nearest' }); }
        }

        function render(data) {
            box.textContent = '';
            links = [];
            active = -1;

            if (!data.groups.length) {
                box.appendChild(el('p', 'px-3 py-4 text-sm text-slate-500', 'Aucun résultat pour « ' + data.term + ' ».'));
                open();
                return;
            }

            data.groups.forEach(function (group) {
                box.appendChild(el('p', 'px-3 pb-1 pt-2 text-[11px] font-bold uppercase tracking-wide text-slate-400', group.label));

                group.items.forEach(function (item) {
                    var link = el('a', 'flex items-center gap-3 rounded-xl px-3 py-2 text-sm hover:bg-slate-50');
                    link.href = item.url;
                    link.setAttribute('role', 'option');

                    if (item.image) {
                        var img = el('img', 'h-9 w-9 shrink-0 rounded-full object-cover');
                        img.src = item.image;
                        img.alt = '';
                        link.appendChild(img);
                    } else {
                        link.appendChild(el('span', 'grid h-9 w-9 shrink-0 place-items-center rounded-full bg-rose-50 text-xs font-bold text-tlink-blue', (item.title || '?').charAt(0).toUpperCase()));
                    }

                    var text = el('span', 'min-w-0');
                    text.appendChild(el('span', 'block truncate font-semibold text-slate-800', item.title));
                    if (item.subtitle) { text.appendChild(el('span', 'block truncate text-xs text-slate-500', item.subtitle)); }
                    link.appendChild(text);

                    box.appendChild(link);
                    links.push(link);
                });
            });

            var all = el('a', 'mt-1 block rounded-xl border-t border-slate-100 px-3 py-2.5 text-center text-sm font-semibold text-tlink-blue hover:bg-slate-50', 'Voir tous les résultats');
            all.href = data.all_url;
            box.appendChild(all);
            links.push(all);
            open();
        }

        function fetchSuggestions() {
            var term = input.value.trim();
            if (term.length < 2) { close(); return; }

            if (pending) { pending.abort(); }
            pending = new AbortController();

            fetch(endpoint + '?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: pending.signal
            }).then(function (response) {
                return response.ok ? response.json() : null;
            }).then(function (data) {
                if (data && input.value.trim() === data.term) { render(data); }
            }).catch(function () { /* requête annulée ou réseau indisponible : la recherche complète reste possible */ });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(fetchSuggestions, 220);
        });

        input.addEventListener('focus', function () {
            if (links.length) { open(); }
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { close(); return; }
            if (box.hidden || !links.length) { return; }

            if (event.key === 'ArrowDown') { event.preventDefault(); setActive((active + 1) % links.length); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); setActive(active <= 0 ? links.length - 1 : active - 1); }
            else if (event.key === 'Enter' && active >= 0) { event.preventDefault(); window.location.href = links[active].href; }
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) { close(); }
        });
    })();
</script>
