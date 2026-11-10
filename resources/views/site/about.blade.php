@extends('site.layouts.site')

@section('title', __('site.about.title'))
@section('flush_footer', '1')

@push('head')
    <script>document.documentElement.classList.add('js-anim');</script>
@endpush

@php
    $presidentMessage = $site->optional('president_message');
    $presidentName = $site->stored('president_name') ?: ($president['name'] ?? '');
    $presidentPhoto = $site->imageUrl('president_photo') ?: ($president['photo'] ?? null);
    $values = [1, 2, 3];
    $slogans = array_values(array_filter(array_map(fn (int $n): string => trim($site->get('slogan_'.$n)), [1, 2, 3]), fn (string $slogan): bool => $slogan !== ''));
@endphp

@section('content')
    @include('site.partials.page-header', [
        'title' => __('site.about.title'),
        'kicker' => __('site.about.kicker'),
        'image' => $site->heroImage('hero_bg_mark_a', asset('hero/hero-mark-a.webp')),
    ])

    {{-- Chiffres clés --}}
    <section class="bg-ink text-white" aria-label="{{ __('site.about.title') }}">
        <div class="mx-auto grid max-w-6xl grid-cols-2 md:grid-cols-4">
            @foreach(['members', 'clubs', 'governorates', 'events'] as $key)
                <div class="px-4 py-7 text-center {{ $loop->first ? '' : 'md:border-s md:border-white/10' }}">
                    <p class="font-display text-3xl font-extrabold text-brand-600 md:text-4xl">{{ number_format($stats[$key], 0, ',', ' ') }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-300">{{ __('site.stats.'.$key) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Présentation --}}
    <section class="bg-white py-16 md:py-24">
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 lg:grid-cols-12">
            <div class="site-prose lg:col-span-7" data-reveal>
                @foreach(preg_split('/\R{2,}/u', $site->get('about_body')) as $paragraph)
                    <p class="{{ $loop->first ? 'text-xl font-semibold leading-relaxed text-ink' : 'text-slate-700' }}">{!! nl2br(e(trim($paragraph))) !!}</p>
                @endforeach
            </div>

            <figure class="relative lg:col-span-5" data-reveal style="--i: 1">
                <img src="{{ $site->heroImage('hero_bg_main', asset('hero/hero-main.webp')) }}" alt="" loading="lazy" class="aspect-[4/5] w-full rounded-3xl object-cover shadow-2xl shadow-black/20">
                <span class="absolute -bottom-3 start-8 h-2 w-28 rounded-full bg-brand-600" aria-hidden="true"></span>
            </figure>
        </div>
    </section>

    {{-- Mot du président --}}
    @if($presidentMessage !== '')
        <section id="president" class="scroll-mt-24 bg-brand-50 py-16 md:py-24" aria-labelledby="president-title">
            <div class="mx-auto max-w-6xl px-4">
            <div class="president-card" data-reveal>
                <figure class="president-photo">
                    @if($presidentPhoto)
                        <img src="{{ $presidentPhoto }}" alt="{{ $presidentName }}" loading="lazy" decoding="async">
                    @else
                        <span class="president-initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($presidentName !== '' ? $presidentName : __('site.name'), 0, 1)) }}</span>
                    @endif
                </figure>

                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-brand-600">{{ __('site.about.kicker') }}</p>
                    <h2 id="president-title" class="mt-2 font-display text-3xl font-extrabold text-ink">{{ __('site.about.president_title') }}</h2>
                    <blockquote class="president-quote mt-4">
                        @foreach(preg_split('/\R{2,}/u', $presidentMessage) as $paragraph)
                            <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                        @endforeach
                    </blockquote>
                    <p class="mt-5 font-display text-lg font-bold text-brand-800">
                        {{ $presidentName }}
                    </p>
                    <p class="text-sm text-slate-600">{{ $site->get('president_role') }}</p>
                </div>
            </div>
            </div>
        </section>
    @endif

    {{-- Mission et vision --}}
    <section id="mission" class="scroll-mt-24 bg-white py-16 md:py-24">
        <div class="mx-auto grid max-w-6xl gap-6 px-4 md:grid-cols-2">
        <article class="mv-card" data-reveal>
            <svg viewBox="0 0 24 24" class="relative h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg>
            <h2 class="relative mt-4 font-display text-2xl font-bold">{{ __('site.about.mission_title') }}</h2>
            <p class="relative mt-3 leading-7 text-white/90">{{ $site->get('mission') }}</p>
        </article>
        <article class="mv-card mv-card--vision" data-reveal style="--i: 1">
            <svg viewBox="0 0 24 24" class="relative h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
            <h2 class="relative mt-4 font-display text-2xl font-bold">{{ __('site.about.vision_title') }}</h2>
            <p class="relative mt-3 leading-7 text-white/90">{{ $site->get('vision') }}</p>
        </article>
        </div>
    </section>

    {{-- Valeurs --}}
    <section class="bg-brand-50 py-16 md:py-24" aria-labelledby="values-title">
        <div class="mx-auto max-w-6xl px-4">
        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-brand-600" data-reveal>{{ __('site.about.kicker') }}</p>
        <h2 id="values-title" class="mt-2 font-display text-3xl font-extrabold text-ink md:text-5xl" data-reveal>{{ __('site.about.values_title') }}</h2>
        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @foreach($values as $i => $n)
                <article class="site-card value-card" data-reveal style="--i: {{ $i }}">
                    <span class="value-num" aria-hidden="true">{{ sprintf('%02d', $n) }}</span>
                    <h3 class="relative font-display text-lg font-bold text-brand-800">{{ $site->get('value_'.$n.'_title') }}</h3>
                    <p class="relative mt-2 text-sm leading-6 text-slate-600">{{ $site->get('value_'.$n.'_text') }}</p>
                </article>
            @endforeach
        </div>
        </div>
    </section>

    {{-- Slogans --}}
    @if($slogans !== [])
        <section class="bg-white px-4 py-16 md:py-24" aria-label="{{ __('site.about.slogans_label') }}">
            <div class="mx-auto max-w-6xl">
            <ul class="slogan-band" data-reveal>
                @foreach($slogans as $i => $slogan)
                    <li class="slogan-item" style="--i: {{ $i }}">{{ $slogan }}</li>
                @endforeach
            </ul>
            </div>
        </section>
    @endif

    {{-- Équipe et espaces --}}
    @include('site.partials.about-spaces', ['spaces' => $spaces])

    {{-- Newsletter --}}
    @include('site.partials.newsletter')
@endsection
