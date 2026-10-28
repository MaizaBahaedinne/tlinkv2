<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-white-32x32.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-white-16x16.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'T.Link | Fondation Tunivisions' }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/main.ts'])
    @endif
</head>
<body>
    @if (session('status'))
        <div class="fixed inset-x-0 top-3 z-50 mx-auto w-full max-w-xl rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white shadow-soft">
            {{ session('status') }}
        </div>
    @endif

    @yield('content')

    @auth
        @if (auth()->user()->onboarding_completed_at === null && ! request()->routeIs('info-session.*'))
            @include('partials.account-gate')
        @endif
    @endauth

@include('partials.impersonation-banner')
</body>
</html>
