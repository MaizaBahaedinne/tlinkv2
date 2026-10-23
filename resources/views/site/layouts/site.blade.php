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
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/site.css'])
    @endif
    @stack('head')
</head>
<body class="min-h-screen bg-brand-50 font-sans text-slate-800 antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">{{ __('site.skip') }}</a>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('site.home') }}" class="flex items-center gap-3" aria-label="{{ __('site.name') }}">
                <picture>
                    <source srcset="{{ asset('logoFondation.webp') }}" type="image/webp">
                    <img src="{{ asset('logoFondation.png') }}" alt="{{ __('site.name') }}" class="h-10 w-auto sm:h-11" loading="eager" decoding="async">
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
                        <span aria-hidden="true">🌐</span>
                        <span>{{ $locales[$locale]['name'] }}</span>
                        <span aria-hidden="true" class="text-xs">▾</span>
                    </summary>
                    <ul class="absolute end-0 z-50 mt-2 min-w-40 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        @foreach($locales as $code => $meta)
                            <li>
                                <a href="{{ $routeName ? route($routeName, ['locale' => $code] + $route->parameters() + request()->query()) : route('site.home', ['locale' => $code]) }}"
                                   hreflang="{{ $code }}" lang="{{ $code }}"
                                   class="flex items-center justify-between gap-3 px-4 py-2 text-sm {{ $code === $locale ? 'bg-brand-50 font-bold text-brand-800' : 'text-slate-700 hover:bg-slate-100' }}"
                                   @if($code === $locale) aria-current="true" @endif>
                                    <span>{{ $meta['name'] }}</span>
                                    @if($code === $locale)<span aria-hidden="true">✓</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
                <a href="{{ route('register') }}" class="hidden rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800 sm:inline-block">{{ __('site.nav.join') }}</a>
                <a href="{{ route('home') }}" class="hidden rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 md:inline-block">{{ __('site.nav.members') }}</a>
            </div>
        </div>

        <details class="border-t border-slate-100 lg:hidden">
            <summary class="cursor-pointer list-none px-4 py-2 text-sm font-semibold text-brand-800">☰ {{ __('site.footer.navigation') }}</summary>
            <nav class="grid gap-1 px-4 pb-3">
                @foreach($nav as $name => $label)
                    <a href="{{ route($name) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ $label }}</a>
                @endforeach
                <a href="{{ route('register') }}" class="rounded-lg px-3 py-2 text-sm font-bold text-brand-600">{{ __('site.nav.join') }}</a>
                <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-700">{{ __('site.nav.members') }}</a>
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
                <ul class="mt-3 grid gap-1 text-sm">
                    @foreach(['facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'linkedin_url' => 'LinkedIn', 'youtube_url' => 'YouTube'] as $key => $label)
                        @if($url = $site->link($key))
                            <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="hover:text-white">{{ $label }}</a></li>
                        @endif
                    @endforeach
                    @if($email = $site->get('contact_email'))
                        <li><a href="mailto:{{ $email }}" class="hover:text-white">{{ $email }}</a></li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 px-4 py-4 text-center text-xs">
            © {{ now()->year }} {{ __('site.name') }}. {{ __('site.footer.rights') }} · {{ __('site.footer.powered') }}
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
