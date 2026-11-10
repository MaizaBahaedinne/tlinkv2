@extends('site.layouts.site')

@section('flush_footer', '1')

@section('content')
    @php
        $heroMain = $site->heroImage('hero_bg_main', asset('hero/hero-main.webp'));
        $heroSlide2 = $site->heroImage('hero_bg_mark_a', asset('hero/hero-mark-a.webp'));
        $heroSlide3 = $site->heroImage('hero_bg_mark_b', asset('hero/hero-mark-b.webp'));
        $missionPhotos = [
            1 => $site->heroImage('mission_1_photo', asset('hero/hero-mark-b.webp')),
            2 => $site->heroImage('mission_2_photo', asset('hero/hero-main.webp')),
            3 => $site->heroImage('mission_3_photo', asset('hero/hero-mark-a.webp')),
        ];
        $featured = $news->first();
        $otherNews = $news->slice(1)->values();
    @endphp

    {{-- 1. Hero immersif --}}
    <section class="hero-shell hero-immersive relative overflow-hidden text-white">
        <div class="hero-bg-layer hero-carousel" aria-hidden="true">
            <div class="hero-slide hero-slide-1" style="--hero-slide-image:url('{{ $heroMain }}');"></div>
            <div class="hero-slide hero-slide-2" style="--hero-slide-image:url('{{ $heroSlide2 }}');"></div>
            <div class="hero-slide hero-slide-3" style="--hero-slide-image:url('{{ $heroSlide3 }}');"></div>
        </div>
        <div class="hero-bg-layer hero-overlay" aria-hidden="true"></div>

        <div class="hero-content mx-auto w-full max-w-6xl px-4 pb-16 pt-24 md:pb-24 md:pt-32">
            <p class="hero-kicker inline-flex items-center gap-3 text-xs font-extrabold uppercase tracking-[0.22em] text-white">
                <span class="h-1 w-10 rounded-full bg-brand-600" aria-hidden="true"></span>{{ __('site.tagline') }}
            </p>
            <h1 class="hero-title mt-5 max-w-4xl font-display text-4xl font-extrabold leading-[1.05] md:text-6xl lg:text-7xl">{{ $site->get('hero_title') }}</h1>
            <p class="hero-subtitle mt-6 max-w-2xl text-lg text-slate-200 md:text-xl">{{ $site->get('hero_subtitle') }}</p>
            <div class="hero-actions mt-9 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-full bg-brand-600 px-7 py-3.5 font-bold text-white shadow-lg shadow-brand-600/30 hover:bg-brand-800">{{ __('site.home.cta_join') }}</a>
                <a href="{{ route('site.clubs') }}" class="rounded-full bg-white px-7 py-3.5 font-bold text-ink hover:bg-brand-100">{{ __('site.home.cta_clubs') }}</a>
            </div>
        </div>
    </section>

    {{-- Chiffres clés --}}
    <section class="bg-ink text-white" aria-label="{{ __('site.home.presence_kicker') }}">
        <div class="mx-auto grid max-w-6xl grid-cols-2 md:grid-cols-4">
            @foreach(['members', 'clubs', 'governorates', 'events'] as $key)
                <div class="px-4 py-7 text-center {{ $loop->first ? '' : 'md:border-s md:border-white/10' }}">
                    <p class="font-display text-3xl font-extrabold text-brand-600 md:text-4xl">{{ number_format($stats[$key], 0, ',', ' ') }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __('site.stats.'.$key) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 2. Nos missions --}}
    <section class="bg-white py-16 md:py-24">
        <div class="mx-auto max-w-6xl px-4">
            <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-brand-600">{{ __('site.home.missions_kicker') }}</p>
            <h2 class="mt-3 max-w-3xl font-display text-3xl font-extrabold text-ink md:text-5xl">{{ __('site.home.pillars_title') }}</h2>
            <div class="mt-10 grid gap-5 md:grid-cols-3">
                @foreach([1, 2, 3] as $i)
                    <article class="group relative isolate overflow-hidden rounded-3xl bg-ink text-white shadow-xl shadow-black/10">
                        <img src="{{ $missionPhotos[$i] }}" alt="" loading="lazy" class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black via-black/60 to-black/5" aria-hidden="true"></div>
                        <div class="flex min-h-[26rem] flex-col justify-end p-7 md:min-h-[34rem]">
                            <span class="font-display text-5xl font-extrabold text-brand-600">0{{ $i }}</span>
                            <h3 class="mt-2 font-display text-2xl font-extrabold">{{ $site->get('pillar_'.$i.'_title') }}</h3>
                            <p class="mt-3 text-sm leading-relaxed text-slate-200">{{ $site->get('pillar_'.$i.'_text') }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 3. Notre présence en Tunisie --}}
    <section class="bg-ink py-16 text-white md:py-24">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-brand-600">{{ __('site.home.presence_kicker') }}</p>
                <h2 class="mt-3 font-display text-3xl font-extrabold md:text-5xl">{{ __('site.home.clubs_title') }}</h2>
                <p class="mt-5 text-lg text-slate-300">{{ __('site.home.clubs_text', ['clubs' => $stats['clubs'], 'governorates' => $stats['governorates']]) }}</p>
                <dl class="mt-8 grid grid-cols-2 gap-4">
                    <div class="rounded-2xl bg-white/10 p-5">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __('site.stats.clubs') }}</dt>
                        <dd class="mt-1 font-display text-4xl font-extrabold text-white">{{ number_format($stats['clubs'], 0, ',', ' ') }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/10 p-5">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __('site.stats.governorates') }}</dt>
                        <dd class="mt-1 font-display text-4xl font-extrabold text-white">{{ number_format($stats['governorates'], 0, ',', ' ') }}</dd>
                    </div>
                </dl>
                <a href="{{ route('site.clubs') }}" class="mt-8 inline-block rounded-full bg-brand-600 px-7 py-3.5 font-bold text-white hover:bg-brand-800">{{ __('site.home.clubs_cta') }}</a>
            </div>
            <div class="lg:col-span-3">
                <div id="clubs-map" class="clubs-map overflow-hidden rounded-3xl shadow-2xl shadow-black/40"
                     data-markers="{{ json_encode($markers, JSON_UNESCAPED_UNICODE) }}"
                     data-clubs-label="{{ __('site.clubs.map_clubs') }}"
                     data-view-label="{{ __('site.clubs.map_view') }}"
                     role="region" aria-label="{{ __('site.clubs.map') }}"></div>
                <p class="mt-3 text-sm text-slate-400">{{ __('site.home.map_hint') }}</p>
            </div>
        </div>
    </section>

    {{-- 4. Rejoindre la communauté : trois étapes --}}
    <section class="bg-white py-16 md:py-24">
        <div class="mx-auto max-w-6xl px-4">
            <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-brand-600">{{ __('site.home.steps_kicker') }}</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold text-ink md:text-5xl">{{ __('site.home.steps_title') }}</h2>
            <ol class="mt-10 grid gap-5 md:grid-cols-3">
                @foreach(trans('site.home.steps') as $i => $step)
                    <li class="relative rounded-3xl bg-brand-50 p-8">
                        <span class="font-display text-6xl font-extrabold leading-none text-brand-600">0{{ $i + 1 }}</span>
                        <h3 class="mt-5 font-display text-xl font-extrabold text-ink">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $step['text'] }}</p>
                        @unless($loop->last)
                            <span class="absolute -end-4 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-ink text-white md:flex rtl:rotate-180" aria-hidden="true">→</span>
                        @endunless
                    </li>
                @endforeach
            </ol>
            <div class="mt-10">
                <a href="{{ route('register') }}" class="inline-block rounded-full bg-ink px-7 py-3.5 font-bold text-white hover:bg-brand-600">{{ __('site.home.cta_join') }}</a>
            </div>
        </div>
    </section>

    {{-- 5. Actualités et événements --}}
    <section class="bg-brand-50 py-16 md:py-24">
        <div class="mx-auto max-w-6xl px-4">
            <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-brand-600">{{ __('site.home.news_kicker') }}</p>
            <h2 class="mt-3 max-w-3xl font-display text-3xl font-extrabold text-ink md:text-5xl">{{ __('site.home.news_headline') }}</h2>

            <div class="mt-10 grid gap-6 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    @if($featured)
                        @php($featuredCover = $featured->coverUrl())
                        <a href="{{ route('site.news.show', ['news' => $featured->id]) }}" class="group relative isolate block min-h-[24rem] overflow-hidden rounded-3xl bg-ink text-white shadow-xl shadow-black/10 md:min-h-[30rem]">
                            @if($featuredCover)
                                <img src="{{ $featuredCover }}" alt="" loading="lazy" class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                            @else
                                <div class="absolute inset-0 -z-10 bg-gradient-to-br from-brand-800 via-brand-900 to-black" aria-hidden="true"></div>
                            @endif
                            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black via-black/50 to-transparent" aria-hidden="true"></div>
                            <div class="flex min-h-[24rem] flex-col justify-end p-7 md:min-h-[30rem] md:p-9">
                                <div class="flex flex-wrap items-center gap-2">
                                    <time datetime="{{ $featured->published_at->toDateString() }}" class="rounded-full bg-brand-600 px-3 py-1 text-xs font-bold">{{ $featured->published_at->translatedFormat('d F Y') }}</time>
                                    @if(\App\Support\OrgScopes::valid($featured->scope))
                                        <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-bold">{{ $featured->categoryLabel() }}</span>
                                    @endif
                                </div>
                                <h3 class="mt-4 font-display text-2xl font-extrabold leading-tight md:text-4xl">{{ $featured->text('title') }}</h3>
                                <p class="mt-3 line-clamp-2 max-w-2xl text-slate-200">{{ $featured->summary() }}</p>
                                <span class="mt-5 text-sm font-bold">{{ __('site.read_more') }} →</span>
                            </div>
                        </a>
                        @if($otherNews->isNotEmpty())
                            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                                @foreach($otherNews as $item)
                                    @include('site.partials.news-card', ['item' => $item])
                                @endforeach
                            </div>
                        @endif
                        <a href="{{ route('site.news') }}" class="mt-6 inline-block text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.see_all') }} →</a>
                    @else
                        <p class="rounded-3xl bg-white p-8 text-slate-500">{{ __('site.home.no_news') }}</p>
                    @endif
                </div>

                <aside class="rounded-3xl bg-ink p-6 text-white shadow-xl shadow-black/10 md:p-8 lg:col-span-5" aria-label="{{ __('site.nav.events') }}">
                    <div class="flex items-end justify-between gap-4">
                        <h3 class="font-display text-2xl font-extrabold">{{ __('site.events.upcoming') }}</h3>
                        <a href="{{ route('site.events') }}" class="text-sm font-bold text-brand-600 hover:text-white">{{ __('site.see_all') }} →</a>
                    </div>
                    @if($events->isNotEmpty())
                        <div class="mt-4 grid gap-1">
                            @foreach($events as $event)
                                @include('site.partials.event-row', ['event' => $event])
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-slate-400">{{ __('site.home.no_events') }}</p>
                    @endif

                    @if($pastEvents->isNotEmpty())
                        <h3 class="mt-8 border-t border-white/10 pt-6 font-display text-xl font-extrabold">{{ __('site.events.past') }}</h3>
                        <div class="mt-3 grid gap-1">
                            @foreach($pastEvents as $event)
                                @include('site.partials.event-row', ['event' => $event, 'past' => true])
                            @endforeach
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </section>

    {{-- 6. Partenaires et appel à l'action --}}
    @if($partners->isNotEmpty())
        @php($track = collect(range(1, (int) ceil(8 / $partners->count())))->flatMap(fn () => $partners))
        <section class="bg-white py-16 md:py-20" aria-labelledby="partners-title">
            <div class="mx-auto max-w-6xl px-4 text-center">
                <h2 id="partners-title" class="font-display text-3xl font-extrabold text-ink md:text-4xl">{{ __('site.home.partners_title') }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-slate-600">{{ __('site.home.partners_text') }}</p>
            </div>
            <div class="partners-marquee mx-auto mt-10 max-w-7xl" dir="ltr">
                <div class="partners-track">
                    @foreach([false, true] as $copy)
                        <ul class="partners-set" @if($copy) aria-hidden="true" @endif>
                            @foreach($track as $partner)
                                <li>
                                    @php($url = $partner->safeUrl())
                                    @if($url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $partner->name }}" @if($copy) tabindex="-1" @endif class="partner-tile">@else<div title="{{ $partner->name }}" class="partner-tile">@endif
                                        <img src="{{ $partner->logoUrl() }}" alt="{{ $copy ? '' : $partner->name }}" loading="lazy" class="max-h-full max-w-full object-contain">
                                    @if($url)</a>@else</div>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="bg-white px-4 pb-16 md:pb-24">
        <div class="cta-band mx-auto max-w-6xl overflow-hidden rounded-3xl p-8 text-center text-white md:p-14">
            <h2 class="mx-auto max-w-2xl font-display text-3xl font-extrabold md:text-5xl">{{ __('site.home.join_title') }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-lg text-slate-100">{{ __('site.home.join_text') }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="rounded-full bg-white px-7 py-3.5 font-bold text-brand-600 hover:bg-brand-100">{{ __('site.nav.join') }}</a>
                <a href="{{ route('site.contact') }}" class="rounded-full border border-white/60 px-7 py-3.5 font-bold text-white hover:bg-white/10">{{ __('site.nav.contact') }}</a>
            </div>
        </div>
    </section>
@endsection

@push('head')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/site-map.js'])
    @endif
@endpush
