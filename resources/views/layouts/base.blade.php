<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.google-tag')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no">
    @include('partials.favicons', ['defaultIconSet' => 'link'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ route('manifest', [], false) }}">
    <meta name="theme-color" content="#d00031">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="T.Link">
    <title>{{ $title ?? 'T.Link | Fondation Tunivisions' }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/main.ts'])
        {{-- Liens vers l'application installée : sans effet sur ordinateur. --}}
        @vite(['resources/js/app-links.js'])
    @endif
    <script>
        // Pas de zoom : ni pincement, ni double-tap (iPhone ignore user-scalable=no, d'où ces garde-fous).
        ['gesturestart', 'gesturechange', 'gestureend'].forEach(function (name) { document.addEventListener(name, function (event) { event.preventDefault(); }); });
        document.addEventListener('touchmove', function (event) { if (event.touches.length > 1) { event.preventDefault(); } }, { passive: false });
    </script>
</head>
<body class="app-full">
    @include('partials.google-tag-noscript')
    @if (session('status'))
        {{-- Notification discrète en bas à gauche, façon Facebook : fond sombre, icône, fermeture auto après quelques secondes. --}}
        <style>
            .flash-toast{position:fixed;left:16px;bottom:calc(16px + env(safe-area-inset-bottom,0px));z-index:60;display:flex;align-items:center;gap:12px;max-width:min(360px,calc(100vw - 32px));padding:12px 14px;border-radius:10px;background:#242526;color:#e4e6eb;box-shadow:0 12px 28px rgba(0,0,0,.25),0 2px 4px rgba(0,0,0,.2);font-size:14px;line-height:1.35;animation:flash-toast-in .3s ease-out both}
            .flash-toast[data-leaving]{animation:flash-toast-out .25s ease-in both}
            .flash-toast__icon{flex:none;display:grid;place-items:center;width:28px;height:28px;border-radius:9999px;background:#31a24c;color:#fff;font-size:14px;font-weight:700}
            .flash-toast__close{flex:none;margin-left:4px;width:26px;height:26px;border:0;border-radius:9999px;background:transparent;color:#b0b3b8;font-size:16px;line-height:1;cursor:pointer}
            .flash-toast__close:hover{background:rgba(255,255,255,.1);color:#fff}
            @keyframes flash-toast-in{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
            @keyframes flash-toast-out{from{opacity:1;transform:none}to{opacity:0;transform:translateY(12px)}}
            @media (prefers-reduced-motion:reduce){.flash-toast,.flash-toast[data-leaving]{animation:none}}
        </style>
        <div class="flash-toast" id="flash-toast" role="status" aria-live="polite">
            <span class="flash-toast__icon" aria-hidden="true">&#10003;</span>
            <span>{{ session('status') }}</span>
            <button type="button" class="flash-toast__close" id="flash-toast-close" aria-label="Fermer">&times;</button>
        </div>
        <script>
            (function () {
                var toast = document.getElementById('flash-toast');
                if (!toast) { return; }
                function close() {
                    toast.setAttribute('data-leaving', '');
                    setTimeout(function () { toast.remove(); }, 260);
                }
                document.getElementById('flash-toast-close').addEventListener('click', close);
                setTimeout(close, 6000);
            })();
        </script>
    @endif

    @yield('content')

    @guest
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            {{-- Connexion par le navigateur du téléphone : sans effet hors des applications mobiles. --}}
            <meta name="native-auth" content="{{ \App\Support\Domains::url('app', route('native.start', [], false)) }}">
            @vite(['resources/js/native-auth.js'])
        @endif
    @endguest

    @auth
        @include('partials.onboarding-banner')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            {{-- Notifications push : sans effet hors des applications mobiles. --}}
            <meta name="push-devices" content="{{ route('devices.store', [], false) }}" data-logout="{{ route('logout', [], false) }}">
            @vite(['resources/js/native-push.js'])
            @php($webPushKey = in_array(\App\Support\Domains::keyForHost(request()->getHost()), [null, 'app', 'messenger'], true) ? \App\Support\Push\WebPush::publicKey() : null)
            @if ($webPushKey)
                {{-- Notifications push du navigateur (T.Link et Messenger, chacun sur son origine et ses propres abonnements) ; sans effet dans les applications mobiles. --}}
                <meta name="web-push" content="{{ route('devices.web.store', [], false) }}" data-key="{{ $webPushKey }}" data-logout="{{ route('logout', [], false) }}" data-test="{{ route('devices.web.test', [], false) }}" data-required="{{ request()->routeIs('home') ? '1' : '0' }}">
                @vite(['resources/js/web-push.js'])
            @endif
        @endif
    @endauth

@include('partials.impersonation-banner')
@include('partials.role-simulation-banner')
</body>
</html>
