<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.google-tag')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
</head>
<body>
    @include('partials.google-tag-noscript')
    @if (session('status'))
        <div class="fixed inset-x-0 top-3 z-50 mx-auto w-full max-w-xl rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white shadow-soft">
            {{ session('status') }}
        </div>
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
