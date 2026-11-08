@php($iconSet = \App\Support\Domains::iconSet(request()) ?? ($defaultIconSet ?? null))
@if ($iconSet)
    <link rel="icon" href="{{ asset("app-icons/$iconSet/favicon.ico") }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset("app-icons/$iconSet/favicon-32x32.png") }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset("app-icons/$iconSet/favicon-16x16.png") }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset("app-icons/$iconSet/apple-touch-icon.png") }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-white-32x32.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-white-16x16.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
@endif
