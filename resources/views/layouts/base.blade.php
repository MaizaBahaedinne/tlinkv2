<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'T.Link | Fondation Tunivisions' }}</title>
    @vite(['resources/css/app.css', 'resources/js/main.ts'])
</head>
<body>
    @if (session('status'))
        <div class="fixed inset-x-0 top-3 z-50 mx-auto w-full max-w-xl rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white shadow-soft">
            {{ session('status') }}
        </div>
    @endif

    @yield('content')
</body>
</html>
