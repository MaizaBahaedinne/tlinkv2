<!DOCTYPE html>
@php
    $locale = app()->getLocale();
    $locales = config('site.locales');
    $route = request()->route();
    $routeName = $route?->getName();
    // Les pages hors du site (vitrines Store et Academy) n'ont pas de segment de langue : leurs alternatives pointent vers l'accueil du site.
    $isSiteRoute = str_starts_with((string) $routeName, 'site.');
    $nav = [
        'site.home' => __('site.nav.home'),
        'site.about' => __('site.nav.about'),
        'site.projects' => __('site.nav.projects'),
        'site.news' => __('site.nav.news'),
        'site.events' => __('site.nav.events'),
        'site.clubs' => __('site.nav.clubs'),
        'store.showcase' => __('site.nav.store'),
        'academy.showcase' => __('site.nav.academy'),
        'site.contact' => __('site.nav.contact'),
    ];
    // Vitrine d'une application cloisonnée (Store, Academy) : ni le site de la Fondation ni les autres applications dans la navigation.
    $section = \App\Support\Domains::sectionOf(request());
    $isolated = in_array($section, ['store', 'academy'], true);

    if ($isolated) {
        $nav = [$section.'.showcase' => $nav[$section.'.showcase']];
    }

    // Barre de navigation : Clubs, Store et Academy n'y figurent plus (hors vitrine cloisonnée, qui garde son seul lien).
    $mainNav = $isolated ? $nav : array_diff_key($nav, array_flip(['site.clubs', 'store.showcase', 'academy.showcase']));

    $homeUrl = $isolated ? route($section.'.showcase') : route('site.home');
    $joinUrl = match (true) {
        $isolated && auth()->check() => \App\Support\Domains::homeUrl(request()),
        $isolated => \App\Support\Domains::url('app', route('register', [], false)),
        auth()->check() => route('home'),
        default => route('register'),
    };
    $title = trim($__env->yieldContent('title'));
    $description = trim($__env->yieldContent('description'));
    $pageTitle = $title !== '' ? $title.' · '.__('site.name') : __('site.name').' · '.__('site.tagline');
    $metaDescription = $description !== '' ? $description : $site->get('hero_subtitle');

    $siteCssPath = null;
    $canUseViteManifest = false;
    $manifestPath = public_path('build/manifest.json');

    if (file_exists($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $entry = is_array($manifest) ? ($manifest['resources/css/site.css'] ?? null) : null;
        $builtFile = is_array($entry) ? ($entry['file'] ?? null) : null;

        if (is_string($builtFile) && $builtFile !== '' && file_exists(public_path('build/'.$builtFile))) {
            $canUseViteManifest = true;
            $siteCssPath = 'build/'.$builtFile;
        }
    }

    if ($siteCssPath === null) {
        $candidates = array_values(array_filter(
            glob(public_path('build/assets/site-*.css')) ?: [],
            fn (string $f): bool => !str_starts_with(basename($f), 'site-map-')
        ));

        usort($candidates, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        if (!empty($candidates)) {
            $siteCssPath = 'build/assets/'.basename($candidates[0]);
        }
    }
@endphp
<html lang="{{ $locale }}" dir="{{ $locales[$locale]['dir'] }}">
<head>
    @include('partials.google-tag')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.favicons')
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($metaDescription), 160) }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($metaDescription), 160) }}">
    <meta property="og:type" content="{{ trim($__env->yieldContent('og_type')) ?: 'website' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(trim($__env->yieldContent('og_image')) !== '')
        <meta property="og:image" content="{{ url(trim($__env->yieldContent('og_image'))) }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    <meta property="og:locale" content="{{ $locale }}">
    @if($routeName && $isSiteRoute)
        @foreach($locales as $code => $meta)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ route($routeName, ['locale' => $code] + $route->parameters() + request()->query()) }}">
        @endforeach
    @endif
    @if (file_exists(public_path('hot')))
        @vite(['resources/css/site.css'])
    @elseif($siteCssPath)
        <link rel="stylesheet" href="{{ asset($siteCssPath) }}">
    @endif
    @stack('head')
    <style>
        /* En haut de page : barre classique pleine largeur ; au défilement : pilule flottante. */
        #site-header { transition: padding .25s ease; }
        #site-header .site-bar { background: #fff; border-bottom: 1px solid rgb(226 232 240); transition: max-width .25s ease, border-radius .25s ease, box-shadow .25s ease, background-color .25s ease, margin .25s ease; }
        #site-header.is-scrolled { padding: .75rem .75rem 0; }
        #site-header.is-scrolled .site-bar { max-width: 80rem; border-radius: 1rem; border: 1px solid rgb(255 255 255 / .6); background: rgb(255 255 255 / .82); box-shadow: 0 8px 30px rgb(15 23 42 / .1), 0 0 0 1px rgb(15 23 42 / .05); -webkit-backdrop-filter: blur(16px); backdrop-filter: blur(16px); }
        @media (min-width: 1024px) { #site-header.is-scrolled .site-bar { border-radius: 9999px; padding-inline-start: 1.25rem; } }
    </style>
</head>
<body class="min-h-screen bg-brand-50 font-sans text-slate-800 antialiased">
    @include('partials.google-tag-noscript')
    <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('site.skip') }}</a>

    <header id="site-header" class="sticky top-0 z-40">
        <div class="site-bar mx-auto flex items-center justify-between gap-3 px-3 py-2 sm:px-4 lg:px-8">
            <a href="{{ $homeUrl }}" class="flex shrink-0 items-center gap-3" aria-label="{{ __('site.name') }}">
                <picture>
                    <source srcset="{{ asset('logoFondation.webp') }}" type="image/webp">
                    <img src="{{ asset('logoFondation.png') }}" alt="{{ __('site.name') }}" class="h-9 w-auto sm:h-10" loading="eager" decoding="async">
                </picture>
            </a>

            <nav class="hidden items-center gap-0.5 lg:flex" aria-label="{{ __('site.footer.navigation') }}">
                @foreach($mainNav as $name => $label)
                    @php $active = $routeName === $name || str_starts_with((string) $routeName, $name.'.'); @endphp
                    <a href="{{ route($name) }}"
                       class="relative rounded-full px-3.5 py-2 text-sm font-semibold transition-colors {{ $active ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
                       @if($routeName === $name) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                @unless($isolated)
                <details class="relative" id="lang-switcher">
                    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-full px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" aria-label="{{ __('site.language') }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z"/></svg>
                        <span>{{ strtoupper($locale) }}</span>
                    </summary>
                    <ul class="absolute end-0 z-50 mt-3 min-w-40 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-xl">
                        @foreach($locales as $code => $meta)
                            <li>
                                <a href="{{ $isSiteRoute ? route($routeName, ['locale' => $code] + $route->parameters() + request()->query()) : route('site.home', ['locale' => $code]) }}"
                                   hreflang="{{ $code }}" lang="{{ $code }}"
                                   class="flex items-center justify-between gap-3 px-4 py-2 text-sm {{ $code === $locale ? 'bg-brand-50 font-bold text-brand-800' : 'text-slate-700 hover:bg-slate-100' }}"
                                   @if($code === $locale) aria-current="true" @endif>
                                    <span>{{ strtoupper($code) }}</span>
                                    @if($code === $locale)<span aria-hidden="true">✓</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
                @endunless
                <a href="{{ $joinUrl }}" class="hidden items-center gap-1.5 rounded-full bg-gradient-to-r from-brand-600 to-brand-800 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-brand-600/25 transition hover:shadow-lg hover:brightness-110 sm:inline-flex">{{ __('site.nav.members') }} <span aria-hidden="true" class="rtl:rotate-180">→</span></a>
                <button type="button" id="site-menu-toggle" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-700 hover:bg-slate-100 lg:hidden" aria-expanded="false" aria-controls="site-menu-panel" aria-label="{{ __('site.footer.navigation') }}">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path data-icon="open" d="M4 7h16M4 12h16M4 17h16"/><path data-icon="close" class="hidden" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </div>

        <div id="site-menu-panel" class="mx-3 mt-2 hidden sm:mx-auto sm:w-[calc(100%-1.5rem)] max-w-7xl overflow-hidden rounded-2xl border border-white/60 bg-white/95 p-2 shadow-[0_12px_40px_rgba(15,23,42,0.12)] ring-1 ring-slate-900/5 backdrop-blur-xl lg:hidden">
            <nav class="grid gap-1" aria-label="{{ __('site.footer.navigation') }}">
                @foreach($mainNav as $name => $label)
                    @php $active = $routeName === $name || str_starts_with((string) $routeName, $name.'.'); @endphp
                    <a href="{{ route($name) }}" class="rounded-xl px-4 py-3 text-base font-semibold {{ $active ? 'bg-brand-50 text-brand-800' : 'text-slate-700 hover:bg-slate-100' }}">{{ $label }}</a>
                @endforeach
                <a href="{{ $joinUrl }}" class="mt-1 rounded-xl bg-gradient-to-r from-brand-600 to-brand-800 px-4 py-3 text-center text-base font-bold text-white">{{ __('site.nav.members') }}</a>
            </nav>
        </div>
    </header>

    <main id="contenu">
        @yield('content')
    </main>

    <footer class="@hasSection('flush_footer') @else mt-16 @endif bg-ink text-slate-300">
        <div class="h-1 w-full bg-brand-600" aria-hidden="true"></div>
        @php
            $footerGroups = [
                __('site.footer.foundation') => ['site.about', 'site.projects', 'site.news', 'site.events'],
                __('site.footer.community') => ['site.clubs', 'store.showcase', 'academy.showcase', 'site.contact'],
            ];
        @endphp
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <picture>
                    <source srcset="{{ asset('logoFondation-white.webp') }}" type="image/webp">
                    <img src="{{ asset('logoFondation-white.png') }}" alt="{{ __('site.name') }}" class="h-12 w-auto" loading="lazy" decoding="async">
                </picture>
                <p class="mt-4 max-w-xs text-sm leading-relaxed">{{ __('site.tagline') }}</p>
                <ul class="mt-5 flex flex-wrap items-center gap-3 text-sm">
                    @foreach([
                        'facebook_url' => ['label' => 'Facebook', 'svg' => 'M13.5 8H16V5h-2.5C11.6 5 10 6.6 10 8.5V11H8v3h2v6h3v-6h2.3l.7-3H13v-2.5c0-.3.2-.5.5-.5Z'],
                        'instagram_url' => ['label' => 'Instagram', 'svg' => 'M12 8.8A3.2 3.2 0 1 0 12 15.2A3.2 3.2 0 1 0 12 8.8Zm0 5.2A2 2 0 1 1 12 10A2 2 0 1 1 12 14Zm4.2-5.3a.75.75 0 1 0 0-1.5a.75.75 0 1 0 0 1.5ZM12 4.8c2.3 0 2.6 0 3.5.1.8 0 1.2.2 1.5.3.4.2.6.3.9.6.3.3.4.5.6.9.1.3.3.7.3 1.5.1.9.1 1.2.1 3.5s0 2.6-.1 3.5c0 .8-.2 1.2-.3 1.5-.2.4-.3.6-.6.9-.3.3-.5.4-.9.6-.3.1-.7.3-1.5.3-.9.1-1.2.1-3.5.1s-2.6 0-3.5-.1c-.8 0-1.2-.2-1.5-.3-.4-.2-.6-.3-.9-.6-.3-.3-.4-.5-.6-.9-.1-.3-.3-.7-.3-1.5-.1-.9-.1-1.2-.1-3.5s0-2.6.1-3.5c0-.8.2-1.2.3-1.5.2-.4.3-.6.6-.9.3-.3.5-.4.9-.6.3-.1.7-.3 1.5-.3.9-.1 1.2-.1 3.5-.1Zm0-1.8c-2.4 0-2.8 0-3.6.1-1 .1-1.7.2-2.3.4-.6.2-1.2.5-1.8 1.1-.6.6-.9 1.1-1.1 1.8-.2.6-.3 1.3-.4 2.3C3 9.2 3 9.6 3 12s0 2.8.1 3.6c.1 1 .2 1.7.4 2.3.2.6.5 1.2 1.1 1.8.6.6 1.1.9 1.8 1.1.6.2 1.3.3 2.3.4.8.1 1.2.1 3.6.1s2.8 0 3.6-.1c1-.1 1.7-.2 2.3-.4.6-.2 1.2-.5 1.8-1.1.6-.6.9-1.1 1.1-1.8.2-.6.3-1.3.4-2.3.1-.8.1-1.2.1-3.6s0-2.8-.1-3.6c-.1-1-.2-1.7-.4-2.3-.2-.6-.5-1.2-1.1-1.8-.6-.6-1.1-.9-1.8-1.1-.6-.2-1.3-.3-2.3-.4-.8-.1-1.2-.1-3.6-.1Z'],
                        'linkedin_url' => ['label' => 'LinkedIn', 'svg' => 'M6.5 8.5H3.8V20h2.7V8.5Zm-1.3-4A1.6 1.6 0 1 0 5.2 7.7A1.6 1.6 0 1 0 5.2 4.5ZM20 13.4c0-3.1-1.7-4.6-4-4.6-1.8 0-2.6 1-3.1 1.7v-2H10.2c0 .8 0 11.5 0 11.5h2.7v-6.4c0-.3 0-.7.1-.9.2-.7.8-1.5 1.8-1.5 1.3 0 1.9 1 1.9 2.5V20H20v-6.6Z'],
                        'youtube_url' => ['label' => 'YouTube', 'svg' => 'M21.6 8.2a2.8 2.8 0 0 0-2-2c-1.8-.5-7.6-.5-7.6-.5s-5.8 0-7.6.5a2.8 2.8 0 0 0-2 2c-.5 1.8-.5 3.8-.5 3.8s0 2 .5 3.8a2.8 2.8 0 0 0 2 2c1.8.5 7.6.5 7.6.5s5.8 0 7.6-.5a2.8 2.8 0 0 0 2-2c.5-1.8.5-3.8.5-3.8s0-2-.5-3.8ZM10.2 15.8V8.2l6 3.8-6 3.8Z'],
                    ] as $key => $social)
                        @if($url = $site->link($key))
                            <li>
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-slate-200 transition hover:bg-brand-600 hover:text-white" aria-label="{{ $social['label'] }}" title="{{ $social['label'] }}">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="{{ $social['svg'] }}"/></svg>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>

            @if($isolated)
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-white">{{ __('site.footer.navigation') }}</p>
                    <ul class="mt-4 grid gap-2 text-sm">
                        @foreach($nav as $name => $label)
                            <li><a href="{{ route($name) }}" class="hover:text-white">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @else
                @foreach($footerGroups as $groupTitle => $routes)
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wide text-white">{{ $groupTitle }}</p>
                        <ul class="mt-4 grid gap-2 text-sm">
                            @foreach($routes as $name)
                                @isset($nav[$name])
                                    <li><a href="{{ route($name) }}" class="hover:text-white">{{ $nav[$name] }}</a></li>
                                @endisset
                            @endforeach
                            @if($loop->last)
                                <li><a href="{{ $joinUrl }}" class="font-semibold text-white hover:text-brand-100">{{ __('site.nav.members') }}</a></li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            @endif

            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-white">{{ __('site.footer.contact') }}</p>
                <ul class="mt-4 grid gap-2 text-sm">
                    @if($email = $site->get('contact_email'))
                        <li><a href="mailto:{{ $email }}" class="hover:text-white">{{ $email }}</a></li>
                    @endif
                    @if($phone = $site->get('contact_phone'))
                        <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-white" dir="ltr">{{ $phone }}</a></li>
                    @endif
                    @if($address = $site->get('contact_address'))
                        <li>{{ $address }}</li>
                    @endif
                    <li><a href="{{ route('site.contact') }}" class="font-semibold text-white hover:text-brand-100">{{ __('site.nav.contact') }} →</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 px-4 py-4 text-center text-xs">
            © {{ now()->year }} Fondation Tunivisions. Tous droits réservés. · Propulsé par Persomy.com
        </div>
    </footer>
    <script>
        // Menu mobile : ouverture, fermeture et Échap ; la barre devient flottante dès qu'on défile.
        (function () {
            var header = document.getElementById('site-header');
            function onScroll() { header.classList.toggle('is-scrolled', window.scrollY > 24); }
            if (header) { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }); }
            var toggle = document.getElementById('site-menu-toggle');
            var panel = document.getElementById('site-menu-panel');
            if (!toggle || !panel) { return; }
            function set(open) {
                panel.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.querySelector('[data-icon=open]').classList.toggle('hidden', open);
                toggle.querySelector('[data-icon=close]').classList.toggle('hidden', !open);
            }
            toggle.addEventListener('click', function () { set(toggle.getAttribute('aria-expanded') !== 'true'); });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { set(false); } });
        })();
    </script>
    <script>
        // Ferme la liste des langues au clic ailleurs ou avec Échap.
        (function () {
            var menu = document.getElementById('lang-switcher');
            if (!menu) { return; }
            document.addEventListener('click', function (event) { if (!menu.contains(event.target)) { menu.removeAttribute('open'); } });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { menu.removeAttribute('open'); } });
        })();
    </script>
@stack('scripts')
@include('partials.impersonation-banner')
@include('partials.role-simulation-banner')
</body>
</html>
