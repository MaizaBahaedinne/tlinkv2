@extends('site.layouts.site')

@section('title', $event->title)
@section('description', \Illuminate\Support\Str::limit(strip_tags((string) $event->description), 160))

@section('content')
    <article class="mx-auto mt-10 max-w-3xl px-4">
        <a href="{{ route('site.events') }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">← {{ __('site.events.title') }}</a>

        <h1 class="mt-4 font-display text-3xl font-bold text-brand-900">{{ $event->title }}</h1>

        @if($coverUrl = $event->coverUrl())
            <img src="{{ $coverUrl }}" alt="{{ $event->title }}" class="mt-6 max-h-[28rem] w-full rounded-xl object-cover">
        @endif

        <dl class="site-card mt-6 grid gap-4 p-5 sm:grid-cols-2">
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
            @if($priceLabel = $event->priceLabel())
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

        @if($event->status === 'published' && $event->starts_at->isFuture())
            <div class="mt-8 rounded-xl bg-brand-100 p-5 text-sm font-semibold text-brand-900">
                {{ __('site.events.register') }}
                <span class="mt-3 flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="rounded-lg bg-brand-800 px-4 py-2 text-white hover:bg-brand-900">{{ __('site.nav.members') }}</a>
                    <a href="{{ route('register') }}" class="rounded-lg border border-brand-800 px-4 py-2 text-brand-800 hover:bg-white">{{ __('site.nav.join') }}</a>
                </span>
            </div>
        @endif
    </article>
@endsection
