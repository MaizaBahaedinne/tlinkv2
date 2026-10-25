<!DOCTYPE html>
@php
    $locale = app()->getLocale();
    $locales = config('site.locales');
    $route = request()->route();
    $routeName = $route?->getName();
    $nav = [
        'site.home' => __('site.nav.home'),
        'site.about' => __('site.nav.about'),
        'site.news' => __('site.nav.news'),
        'site.events' => __('site.nav.events'),
        'site.clubs' => __('site.nav.clubs'),
        'site.contact' => __('site.nav.contact'),
    ];
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
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-white-32x32.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-white-16x16.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($metaDescription), 160) }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($metaDescription), 160) }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ $locale }}">
    @if($routeName)
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
</head>
<body class="min-h-screen bg-brand-50 font-sans text-slate-800 antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('site.skip') }}</a>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="w-full flex items-center justify-between gap-4 px-4 py-3 lg:px-8">
            <a href="{{ route('site.home') }}" class="flex items-center gap-3" aria-label="{{ __('site.name') }}">
                <picture>
                    <source srcset="{{ asset('logoFondation.webp') }}" type="image/webp">
                    <img src="{{ asset('logoFondation.png') }}" alt="{{ __('site.name') }}" class="h-11 w-auto sm:h-12" loading="eager" decoding="async">
                </picture>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('site.footer.navigation') }}">
                @foreach($nav as $name => $label)
                    <a href="{{ route($name) }}"
                       class="rounded-lg px-3 py-2 text-sm font-semibold {{ $routeName === $name || str_starts_with((string) $routeName, $name.'.') ? 'bg-brand-100 text-brand-800' : 'text-slate-600 hover:bg-slate-100' }}"
                       @if($routeName === $name) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <details class="relative" id="lang-switcher">
                    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100" aria-label="{{ __('site.language') }}">
                        <span>{{ strtoupper($locale) }}</span>
                        <span aria-hidden="true" class="text-xs">▾</span>
                    </summary>
                    <ul class="absolute end-0 z-50 mt-2 min-w-40 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        @foreach($locales as $code => $meta)
                            <li>
                                <a href="{{ $routeName ? route($routeName, ['locale' => $code] + $route->parameters() + request()->query()) : route('site.home', ['locale' => $code]) }}"
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
                <a href="{{ auth()->check() ? route('home') : route('register') }}" class="hidden rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800 sm:inline-block">{{ __('site.nav.join') }} / {{ __('site.nav.members') }}</a>
            </div>
        </div>

        <details class="border-t border-slate-100 lg:hidden">
            <summary class="cursor-pointer list-none px-4 py-2 text-sm font-semibold text-brand-800">☰ {{ __('site.footer.navigation') }}</summary>
            <nav class="grid gap-1 px-4 pb-3">
                @foreach($nav as $name => $label)
                    <a href="{{ route($name) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ $label }}</a>
                @endforeach
                <a href="{{ auth()->check() ? route('home') : route('register') }}" class="rounded-lg px-3 py-2 text-sm font-bold text-brand-600">{{ __('site.nav.join') }} / {{ __('site.nav.members') }}</a>
            </nav>
        </details>
    </header>

    <main id="contenu">
        @yield('content')
    </main>

    <footer class="mt-16 bg-brand-900 text-slate-300">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 md:grid-cols-3">
            <div>
                <picture>
                    <source srcset="{{ asset('logoFondation-white.webp') }}" type="image/webp">
                    <img src="{{ asset('logoFondation-white.png') }}" alt="{{ __('site.name') }}" class="h-11 w-auto" loading="lazy" decoding="async">
                </picture>
                <p class="mt-2 text-sm">{{ __('site.tagline') }}</p>
            </div>
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-white">{{ __('site.footer.navigation') }}</p>
                <ul class="mt-3 grid gap-1 text-sm">
                    @foreach($nav as $name => $label)
                        <li><a href="{{ route($name) }}" class="hover:text-white">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-white">{{ __('site.contact.follow') }}</p>
                <ul class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                    @foreach([
                        'facebook_url' => ['label' => 'Facebook', 'svg' => 'M13.5 8H16V5h-2.5C11.6 5 10 6.6 10 8.5V11H8v3h2v6h3v-6h2.3l.7-3H13v-2.5c0-.3.2-.5.5-.5Z'],
                        'instagram_url' => ['label' => 'Instagram', 'svg' => 'M12 8.8A3.2 3.2 0 1 0 12 15.2A3.2 3.2 0 1 0 12 8.8Zm0 5.2A2 2 0 1 1 12 10A2 2 0 1 1 12 14Zm4.2-5.3a.75.75 0 1 0 0-1.5a.75.75 0 1 0 0 1.5ZM12 4.8c2.3 0 2.6 0 3.5.1.8 0 1.2.2 1.5.3.4.2.6.3.9.6.3.3.4.5.6.9.1.3.3.7.3 1.5.1.9.1 1.2.1 3.5s0 2.6-.1 3.5c0 .8-.2 1.2-.3 1.5-.2.4-.3.6-.6.9-.3.3-.5.4-.9.6-.3.1-.7.3-1.5.3-.9.1-1.2.1-3.5.1s-2.6 0-3.5-.1c-.8 0-1.2-.2-1.5-.3-.4-.2-.6-.3-.9-.6-.3-.3-.4-.5-.6-.9-.1-.3-.3-.7-.3-1.5-.1-.9-.1-1.2-.1-3.5s0-2.6.1-3.5c0-.8.2-1.2.3-1.5.2-.4.3-.6.6-.9.3-.3.5-.4.9-.6.3-.1.7-.3 1.5-.3.9-.1 1.2-.1 3.5-.1Zm0-1.8c-2.4 0-2.8 0-3.6.1-1 .1-1.7.2-2.3.4-.6.2-1.2.5-1.8 1.1-.6.6-.9 1.1-1.1 1.8-.2.6-.3 1.3-.4 2.3C3 9.2 3 9.6 3 12s0 2.8.1 3.6c.1 1 .2 1.7.4 2.3.2.6.5 1.2 1.1 1.8.6.6 1.1.9 1.8 1.1.6.2 1.3.3 2.3.4.8.1 1.2.1 3.6.1s2.8 0 3.6-.1c1-.1 1.7-.2 2.3-.4.6-.2 1.2-.5 1.8-1.1.6-.6.9-1.1 1.1-1.8.2-.6.3-1.3.4-2.3.1-.8.1-1.2.1-3.6s0-2.8-.1-3.6c-.1-1-.2-1.7-.4-2.3-.2-.6-.5-1.2-1.1-1.8-.6-.6-1.1-.9-1.8-1.1-.6-.2-1.3-.3-2.3-.4-.8-.1-1.2-.1-3.6-.1Z'],
                        'linkedin_url' => ['label' => 'LinkedIn', 'svg' => 'M6.5 8.5H3.8V20h2.7V8.5Zm-1.3-4A1.6 1.6 0 1 0 5.2 7.7A1.6 1.6 0 1 0 5.2 4.5ZM20 13.4c0-3.1-1.7-4.6-4-4.6-1.8 0-2.6 1-3.1 1.7v-2H10.2c0 .8 0 11.5 0 11.5h2.7v-6.4c0-.3 0-.7.1-.9.2-.7.8-1.5 1.8-1.5 1.3 0 1.9 1 1.9 2.5V20H20v-6.6Z'],
                        'youtube_url' => ['label' => 'YouTube', 'svg' => 'M21.6 8.2a2.8 2.8 0 0 0-2-2c-1.8-.5-7.6-.5-7.6-.5s-5.8 0-7.6.5a2.8 2.8 0 0 0-2 2c-.5 1.8-.5 3.8-.5 3.8s0 2 .5 3.8a2.8 2.8 0 0 0 2 2c1.8.5 7.6.5 7.6.5s5.8 0 7.6-.5a2.8 2.8 0 0 0 2-2c.5-1.8.5-3.8.5-3.8s0-2-.5-3.8ZM10.2 15.8V8.2l6 3.8-6 3.8Z'],
                    ] as $key => $social)
                        @if($url = $site->link($key))
                            <li>
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/25 text-slate-300 transition hover:border-white hover:text-white" aria-label="{{ $social['label'] }}" title="{{ $social['label'] }}">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="{{ $social['svg'] }}"/></svg>
                                </a>
                            </li>
                        @endif
                    @endforeach
                    @if($email = $site->get('contact_email'))
                        <li><a href="mailto:{{ $email }}" class="text-xs hover:text-white">{{ $email }}</a></li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 px-4 py-4 text-center text-xs">
            © {{ now()->year }} Fondation Tunivisions. Tous droits réservés. · Propulsé par Persomy.com
        </div>
    </footer>
    <script>
        // Ferme la liste des langues au clic ailleurs ou avec Échap.
        (function () {
            var menu = document.getElementById('lang-switcher');
            if (!menu) { return; }
            document.addEventListener('click', function (event) { if (!menu.contains(event.target)) { menu.removeAttribute('open'); } });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { menu.removeAttribute('open'); } });
        })();
    </script>
</body>
</html>
