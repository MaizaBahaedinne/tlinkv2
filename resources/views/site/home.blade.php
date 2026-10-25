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

    <section class="mx-auto mt-16 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.scopes_title') }}</h2>
        @php
            $scopeHierarchy = $scopeHierarchy ?? [];
            $scopeRows = [];

            foreach ($scopes as $slug => $label) {
                $depth = 0;
                $cursor = $slug;

                for ($guard = 0; $guard < 20; $guard++) {
                    $parent = $scopeHierarchy[$cursor] ?? null;
                    if (!$parent || !isset($scopes[$parent])) {
                        break;
                    }

                    $depth++;
                    $cursor = $parent;
                }

                $scopeRows[] = [
                    'slug' => $slug,
                    'label' => $label,
                    'parent' => $scopeHierarchy[$slug] ?? null,
                    'depth' => $depth,
                ];
            }

            usort($scopeRows, fn ($a, $b) => $a['depth'] <=> $b['depth'] ?: strcmp($a['label'], $b['label']));
        @endphp

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($scopeRows as $row)
                @php($parentLabel = ($row['parent'] && isset($scopes[$row['parent']])) ? $scopes[$row['parent']] : null)
                <div class="site-card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-display text-lg font-bold text-brand-800">{{ $row['label'] }}</h3>
                        @if($parentLabel)
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">Niveau {{ $row['depth'] }}</span>
                        @else
                            <span class="rounded-full bg-brand-100 px-2.5 py-1 text-[11px] font-bold text-brand-800">Racine</span>
                        @endif
                    </div>

                    @if($parentLabel)
                        <p class="mt-2 text-sm text-slate-600">Hérite de <span class="font-semibold text-slate-700">{{ $parentLabel }}</span></p>
                    @else
                        <p class="mt-2 text-sm text-slate-500">Périmètre parent (niveau principal).</p>
                    @endif
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
