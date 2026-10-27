@extends('site.layouts.site')

@section('content')
    @php
        $heroMain = $site->heroImage('hero_bg_main', asset('logoFondation-white.webp'));
        $heroSlide2 = $site->heroImage('hero_bg_mark_a', $heroMain);
        $heroSlide3 = $site->heroImage('hero_bg_mark_b', $heroMain);
    @endphp

    <section class="hero-shell relative overflow-hidden text-white">
        <div class="hero-bg-layer hero-carousel" aria-hidden="true">
            <div class="hero-slide hero-slide-1" style="--hero-slide-image:url('{{ $heroMain }}');"></div>
            <div class="hero-slide hero-slide-2" style="--hero-slide-image:url('{{ $heroSlide2 }}');"></div>
            <div class="hero-slide hero-slide-3" style="--hero-slide-image:url('{{ $heroSlide3 }}');"></div>
        </div>
        <div class="hero-bg-layer hero-overlay" aria-hidden="true"></div>

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

    <section class="relative z-10 mx-auto -mt-10 max-w-6xl px-4">
        <div class="site-card grid grid-cols-2 gap-6 p-6 text-center md:grid-cols-4">
            @foreach(['members', 'clubs', 'governorates', 'events'] as $key)
                <div>
                    <p class="font-display text-3xl font-bold text-brand-800 md:text-4xl">{{ number_format($stats[$key], 0, ',', ' ') }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ __('site.stats.'.$key) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.pillars_title') }}</h2>
        <div class="mt-6 grid gap-5 md:grid-cols-3">
            @foreach([1, 2, 3] as $i)
                <article class="site-card p-6">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-800 font-display text-sm font-bold text-white">0{{ $i }}</span>
                    <h3 class="mt-4 font-display text-lg font-bold text-brand-800">{{ $site->get('pillar_'.$i.'_title') }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $site->get('pillar_'.$i.'_text') }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <div class="rounded-2xl bg-brand-800 p-8 text-white md:p-10">
            <div class="grid items-center gap-6 md:grid-cols-3">
                <div class="md:col-span-2">
                    <h2 class="font-display text-2xl font-bold">{{ __('site.home.clubs_title') }}</h2>
                    <p class="mt-3 max-w-xl text-slate-200">{{ __('site.home.clubs_text', ['clubs' => $stats['clubs'], 'governorates' => $stats['governorates']]) }}</p>
                </div>
                <div class="md:text-end">
                    <a href="{{ route('site.clubs') }}" class="inline-block rounded-xl bg-accent px-6 py-3 font-bold text-brand-900 hover:brightness-110">{{ __('site.home.clubs_cta') }}</a>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.steps_title') }}</h2>
        <ol class="mt-6 grid gap-5 md:grid-cols-3">
            @foreach(trans('site.home.steps') as $i => $step)
                <li class="site-card p-6">
                    <p class="font-display text-4xl font-bold text-accent">{{ $i + 1 }}</p>
                    <h3 class="mt-2 font-display text-lg font-bold text-brand-800">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <div class="flex items-end justify-between gap-4">
            <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.news_title') }}</h2>
            <a href="{{ route('site.news') }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.see_all') }} →</a>
        </div>
        @if($news->isNotEmpty())
            <div class="mt-6 grid gap-5 md:grid-cols-3">
                @foreach($news as $item)
                    @include('site.partials.news-card', ['item' => $item])
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

    @if($partners->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4" aria-labelledby="partners-title">
            <h2 id="partners-title" class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.partners_title') }}</h2>
            <ul class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                @foreach($partners as $partner)
                    <li>
                        @php($url = $partner->safeUrl())
                        @if($url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $partner->name }}" class="site-card flex h-28 items-center justify-center p-4 transition hover:shadow-md">@else<div title="{{ $partner->name }}" class="site-card flex h-28 items-center justify-center p-4">@endif
                            <img src="{{ $partner->logoUrl() }}" alt="{{ $partner->name }}" loading="lazy" class="max-h-full max-w-full object-contain">
                        @if($url)</a>@else</div>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <div class="rounded-2xl bg-brand-800 p-8 text-center text-white md:p-12">
            <h2 class="font-display text-2xl font-bold">{{ __('site.home.join_title') }}</h2>
            <p class="mx-auto mt-3 max-w-xl text-slate-200">{{ __('site.home.join_text') }}</p>
            <a href="{{ route('register') }}" class="mt-6 inline-block rounded-xl bg-accent px-6 py-3 font-bold text-brand-900 hover:brightness-110">{{ __('site.nav.join') }}</a>
        </div>
    </section>
@endsection
