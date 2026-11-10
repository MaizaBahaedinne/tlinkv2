@php
    $image = $image ?? asset('hero/hero-main.webp');
    $kicker = $kicker ?? __('site.name');
@endphp
{{-- En-tête immersif commun : photographie, voile noir et rouge, grand titre blanc. --}}
<section class="page-hero relative isolate overflow-hidden text-white">
    <img src="{{ $image }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover" loading="eager" decoding="async">
    <div class="page-hero-overlay absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="mx-auto max-w-6xl px-4 pb-14 pt-20 md:pb-20 md:pt-28">
        <p class="hero-kicker inline-flex items-center gap-3 text-xs font-extrabold uppercase tracking-[0.22em] text-white">
            <span class="h-1 w-10 rounded-full bg-brand-600" aria-hidden="true"></span>{{ $kicker }}
        </p>
        <h1 class="hero-title mt-4 max-w-4xl font-display text-4xl font-extrabold leading-[1.05] md:text-6xl">{{ $title }}</h1>
        @isset($intro)
            <p class="hero-subtitle mt-5 max-w-2xl text-lg text-slate-200 md:text-xl">{{ $intro }}</p>
        @endisset
    </div>
</section>
