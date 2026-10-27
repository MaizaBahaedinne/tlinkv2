@extends('site.layouts.site')

@section('title', __('site.about.title'))

@push('head')
    <script>document.documentElement.classList.add('js-anim');</script>
@endpush

@php
    $presidentMessage = $site->optional('president_message');
    $presidentName = $site->stored('president_name') ?: ($president['name'] ?? '');
    $presidentPhoto = $site->imageUrl('president_photo') ?: ($president['photo'] ?? null);
    $values = [1, 2, 3, 4];
@endphp

@section('content')
    @include('site.partials.page-header', ['title' => __('site.about.title')])

    {{-- Présentation et chiffres --}}
    <section class="mx-auto mt-10 grid max-w-6xl gap-10 px-4 lg:grid-cols-3">
        <div class="site-prose lg:col-span-2" data-reveal>
            @foreach(preg_split('/\R{2,}/u', $site->get('about_body')) as $paragraph)
                <p class="text-slate-700">{!! nl2br(e(trim($paragraph))) !!}</p>
            @endforeach
        </div>

        <aside class="grid content-start gap-4">
            @foreach(['members', 'clubs', 'governorates', 'events'] as $i => $key)
                <div class="site-card flex items-center justify-between p-4" data-reveal style="--i: {{ $i }}">
                    <span class="text-sm font-semibold text-slate-600">{{ __('site.stats.'.$key) }}</span>
                    <span class="font-display text-2xl font-bold text-brand-800">{{ number_format($stats[$key], 0, ',', ' ') }}</span>
                </div>
            @endforeach
        </aside>
    </section>

    {{-- Mot du président --}}
    @if($presidentMessage !== '')
        <section id="president" class="mx-auto mt-14 max-w-6xl scroll-mt-24 px-4" aria-labelledby="president-title">
            <div class="president-card" data-reveal>
                <figure class="president-photo">
                    @if($presidentPhoto)
                        <img src="{{ $presidentPhoto }}" alt="{{ $presidentName }}" loading="lazy" decoding="async">
                    @else
                        <span class="president-initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($presidentName !== '' ? $presidentName : __('site.name'), 0, 1)) }}</span>
                    @endif
                </figure>

                <div>
                    <h2 id="president-title" class="font-display text-2xl font-bold text-brand-900">{{ __('site.about.president_title') }}</h2>
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
        </section>
    @endif

    {{-- Mission et vision --}}
    <section class="mx-auto mt-14 grid max-w-6xl gap-6 px-4 md:grid-cols-2">
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
    </section>

    {{-- Valeurs --}}
    <section class="mx-auto mt-14 max-w-6xl px-4" aria-labelledby="values-title">
        <h2 id="values-title" class="font-display text-2xl font-bold text-brand-900" data-reveal>{{ __('site.about.values_title') }}</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($values as $i => $n)
                <article class="site-card value-card" data-reveal style="--i: {{ $i }}">
                    <span class="value-num" aria-hidden="true">{{ sprintf('%02d', $n) }}</span>
                    <h3 class="relative font-display text-lg font-bold text-brand-800">{{ $site->get('value_'.$n.'_title') }}</h3>
                    <p class="relative mt-2 text-sm leading-6 text-slate-600">{{ $site->get('value_'.$n.'_text') }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Équipe et espaces --}}
    @include('site.partials.about-spaces', ['spaces' => $spaces])

    {{-- Newsletter --}}
    @include('site.partials.newsletter')
@endsection
