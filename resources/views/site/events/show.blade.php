@extends('site.layouts.site')

@php
    $description = \Illuminate\Support\Str::limit(strip_tags((string) $event->description), 160);
    $cover = $event->coverUrl();
    $shareUrl = url()->current();
    $shareText = $event->title.' — '.$event->starts_at->translatedFormat('d F Y');
    $encodedUrl = rawurlencode($shareUrl);
    $encodedText = rawurlencode($shareText);
    $isUpcoming = $event->status === 'published' && $event->starts_at->isFuture();
    $isOver = $event->status === 'completed' || $event->starts_at->isPast();
    $place = collect([$event->location, $event->governorate?->name])->filter()->implode(' · ');
    $priceLabel = $event->priceLabel();
    $networks = [
        'Facebook' => ['href' => 'https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl, 'path' => 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.49 0-1.96.93-1.96 1.88v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z'],
        'X' => ['href' => 'https://twitter.com/intent/tweet?text='.$encodedText.'&url='.$encodedUrl, 'path' => 'M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.4l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93Zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41Z'],
        'LinkedIn' => ['href' => 'https://www.linkedin.com/sharing/share-offsite/?url='.$encodedUrl, 'path' => 'M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z'],
        'WhatsApp' => ['href' => 'https://wa.me/?text='.rawurlencode($shareText.' '.$shareUrl), 'path' => 'M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35ZM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88ZM20.46 3.49A11.8 11.8 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.17-3.48-8.42Z'],
    ];
@endphp

@section('title', $event->title)
@section('description', $description)
@section('og_type', 'article')
@section('og_image', (string) $cover)

@section('content')
    {{-- Hero : la couverture de l'événement en grand (ou un dégradé à défaut), date en vignette, titre et partage par-dessus --}}
    <header class="relative isolate flex min-h-[380px] items-end overflow-hidden bg-ink text-white md:min-h-[500px]">
        @if($cover)
            <img src="{{ $cover }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover" loading="eager" decoding="async" fetchpriority="high">
            <div class="absolute inset-0 -z-10 bg-linear-to-t from-black via-black/60 to-black/10"></div>
        @else
            <div class="absolute inset-0 -z-10 bg-linear-to-br from-ink via-brand-900 to-brand-600"></div>
            <div class="absolute -end-24 -top-24 -z-10 h-80 w-80 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-32 start-1/3 -z-10 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
        @endif

        <div class="mx-auto w-full max-w-5xl px-4 pb-8 pt-24 md:pb-12">
            <a href="{{ route('site.events') }}" class="text-sm font-bold text-white/80 hover:text-white">← {{ __('site.events.title') }}</a>

            <div class="mt-5 flex flex-col gap-5 md:flex-row md:items-end md:gap-7">
                <div class="flex h-24 w-24 shrink-0 flex-col items-center justify-center rounded-2xl bg-white text-brand-900 shadow-xl md:h-28 md:w-28" aria-hidden="true">
                    <span class="text-xs font-bold uppercase tracking-widest text-brand-600">{{ $event->starts_at->translatedFormat('M') }}</span>
                    <span class="font-display text-4xl font-bold leading-none md:text-5xl">{{ $event->starts_at->format('d') }}</span>
                    <span class="mt-1 text-xs font-semibold text-slate-500">{{ $event->starts_at->format('Y') }}</span>
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                        @if($isUpcoming)
                            <span class="rounded-full bg-brand-600 px-3 py-1 text-white">{{ __('site.events.upcoming') }}</span>
                        @elseif($isOver)
                            <span class="rounded-full bg-white/25 px-3 py-1 backdrop-blur">{{ __('site.events.status_completed') }}</span>
                        @endif
                        @if(\App\Support\OrgScopes::valid($event->scope))
                            <span class="rounded-full bg-white/20 px-3 py-1 backdrop-blur">{{ __('site.scopes.'.$event->scope.'.name') }}</span>
                        @endif
                        @if($priceLabel)
                            <span class="rounded-full bg-white/20 px-3 py-1 backdrop-blur">{{ $priceLabel }}</span>
                        @endif
                    </div>

                    <h1 class="mt-3 font-display text-3xl font-bold leading-tight md:text-5xl">{{ $event->title }}</h1>

                    <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-white/90 md:text-base">
                        <li class="inline-flex items-center gap-2">
                            <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <span>
                                {{ $event->starts_at->translatedFormat('l d F Y · H:i') }}
                                @if($event->ends_at && ! $event->ends_at->isSameDay($event->starts_at))
                                    <span class="font-normal text-white/75">— {{ __('site.events.until', ['date' => $event->ends_at->translatedFormat('d F Y')]) }}</span>
                                @endif
                            </span>
                        </li>
                        @if($place !== '')
                            <li class="inline-flex items-center gap-2">
                                <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span>{{ $place }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            {{-- Partage sur les réseaux sociaux --}}
            <div class="mt-7 flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('site.events.share') }}">
                <span class="me-1 text-xs font-bold uppercase tracking-wide text-white/80">{{ __('site.events.share') }}</span>
                @foreach($networks as $name => $network)
                    <a href="{{ $network['href'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/15 px-3.5 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white hover:text-brand-800" aria-label="{{ __('site.news.share_on', ['network' => $name]) }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="{{ $network['path'] }}"/></svg>
                        <span class="hidden sm:inline">{{ $name }}</span>
                    </a>
                @endforeach
                <button type="button" data-copy-link="{{ $shareUrl }}" data-copied="{{ __('site.news.link_copied') }}" class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/15 px-3.5 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white hover:text-brand-800">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg>
                    <span data-label>{{ __('site.news.copy_link') }}</span>
                </button>
            </div>
        </div>
    </header>

    <article class="mx-auto mt-10 max-w-3xl px-4">
        <dl class="site-card grid gap-4 p-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('site.events.when') }}</dt>
                <dd class="mt-1 font-semibold text-brand-900">
                    {{ $event->starts_at->translatedFormat('d F Y · H:i') }}
                    @if($event->ends_at && ! $event->ends_at->isSameDay($event->starts_at))
                        <span class="block text-sm font-normal text-slate-600">{{ __('site.events.until', ['date' => $event->ends_at->translatedFormat('d F Y')]) }}</span>
                    @endif
                </dd>
            </div>
            @if($event->location || $event->governorate)
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('site.events.where') }}</dt>
                    <dd class="mt-1 font-semibold text-brand-900">
                        {{ $event->location }}
                        @if($event->governorate)
                            <span class="block {{ $event->location ? 'text-sm font-normal text-slate-600' : '' }}">{{ __('site.events.governorate') }} : {{ $event->governorate->name }}</span>
                        @endif
                    </dd>
                </div>
            @endif
            @if($priceLabel)
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('site.events.price') }}</dt>
                    <dd class="mt-1 font-semibold text-brand-900">{{ $priceLabel }}</dd>
                </div>
            @endif
        </dl>

        @if($event->description)
            <div class="site-prose mt-6 text-slate-700">
                @foreach(preg_split('/\R{2,}/u', trim($event->description)) as $paragraph)
                    <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                @endforeach
            </div>
        @endif

        @if($isUpcoming)
            <div class="mt-8 rounded-3xl bg-brand-50 p-6 text-sm font-semibold text-ink">
                {{ __('site.events.register') }}
                <span class="mt-3 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="rounded-full bg-brand-600 px-5 py-2.5 text-white hover:bg-ink">{{ __('site.nav.members') }}</a>
                    <a href="{{ route('register') }}" class="rounded-full border border-brand-600 px-5 py-2.5 text-brand-600 hover:bg-white">{{ __('site.nav.join') }}</a>
                </span>
            </div>
        @endif
    </article>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-copy-link]').forEach(function (button) {
        button.addEventListener('click', function () {
            var label = button.querySelector('[data-label]');
            var original = label.textContent;
            var done = function () {
                label.textContent = button.dataset.copied;
                setTimeout(function () { label.textContent = original; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(button.dataset.copyLink).then(done);
            } else {
                window.prompt('', button.dataset.copyLink);
            }
        });
    });
</script>
@endpush
