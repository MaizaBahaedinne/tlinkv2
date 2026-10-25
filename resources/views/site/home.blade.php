@extends('site.layouts.site')

@section('content')
    @php
        $heroMain = $site->heroImage('hero_bg_main', asset('logoFondation-white.webp'));
        $heroMarkA = $site->heroImage('hero_bg_mark_a', asset('favicon-white-180x180.png'));
        $heroMarkB = $site->heroImage('hero_bg_mark_b', asset('favicon-white-180x180.png'));
    @endphp

    <section class="hero-shell relative overflow-hidden text-white">
        <div class="hero-bg-layer hero-bg-photo" style="--hero-main-image:url('{{ $heroMain }}');" aria-hidden="true"></div>
        <div class="hero-bg-layer hero-bg-mark hero-bg-mark-a" style="--hero-mark-a-image:url('{{ $heroMarkA }}');" aria-hidden="true"></div>
        <div class="hero-bg-layer hero-bg-mark hero-bg-mark-b" style="--hero-mark-b-image:url('{{ $heroMarkB }}');" aria-hidden="true"></div>
        <div class="hero-sheen" aria-hidden="true"></div>

        <div class="hero-content mx-auto max-w-6xl px-4 py-16 md:py-24">
            <p class="hero-kicker text-sm font-bold uppercase tracking-widest text-accent">{{ __('site.tagline') }}</p>
            <h1 class="hero-title mt-3 max-w-3xl font-display text-4xl font-bold leading-tight md:text-5xl">{{ $site->get('hero_title') }}</h1>
            <p class="hero-subtitle mt-5 max-w-2xl text-lg text-slate-200">{{ $site->get('hero_subtitle') }}</p>
            <div class="hero-actions mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-xl bg-accent px-6 py-3 font-bold text-brand-900 hover:brightness-110">{{ __('site.home.cta_join') }}</a>
                <a href="{{ route('site.clubs') }}" class="rounded-xl border border-white/40 px-6 py-3 font-bold hover:bg-white/10">{{ __('site.home.cta_clubs') }}</a>
            </div>
        </div>
    </section>

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.scopes_title') }}</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($scopes as $slug => $label)
                <div class="site-card p-5">
                    <h3 class="font-display text-lg font-bold text-brand-800">{{ __('site.scopes.'.$slug.'.name') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('site.scopes.'.$slug.'.text') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <div class="flex items-end justify-between gap-4">
            <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.news_title') }}</h2>
            <a href="{{ route('site.news') }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.see_all') }} →</a>
        </div>
        @if($news->isNotEmpty())
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                @foreach($news as $post)
                    @include('site.partials.post-card', ['post' => $post])
                @endforeach
            </div>
        @else
            <p class="mt-6 text-slate-500">{{ __('site.home.no_news') }}</p>
        @endif
    </section>

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <div class="flex items-end justify-between gap-4">
            <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.events_title') }}</h2>
            <a href="{{ route('site.events') }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.see_all') }} →</a>
        </div>
        @if($events->isNotEmpty())
            <div class="mt-6 grid gap-4 md:grid-cols-3">
                @foreach($events as $event)
                    @include('site.partials.event-card', ['event' => $event])
                @endforeach
            </div>
        @else
            <p class="mt-6 text-slate-500">{{ __('site.home.no_events') }}</p>
        @endif
    </section>

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <div class="rounded-2xl bg-brand-800 p-8 text-center text-white md:p-12">
            <h2 class="font-display text-2xl font-bold">{{ __('site.home.join_title') }}</h2>
            <p class="mx-auto mt-3 max-w-xl text-slate-200">{{ __('site.home.join_text') }}</p>
            <a href="{{ route('register') }}" class="mt-6 inline-block rounded-xl bg-accent px-6 py-3 font-bold text-brand-900 hover:brightness-110">{{ __('site.nav.join') }}</a>
        </div>
    </section>
@endsection
