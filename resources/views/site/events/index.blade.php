@extends('site.layouts.site')

@section('title', __('site.events.title'))
@section('flush_footer', '1')

@section('content')
    @include('site.partials.page-header', [
        'title' => __('site.events.title'),
        'intro' => __('site.events.intro'),
        'kicker' => __('site.events.kicker'),
        'image' => $site->heroImage('hero_bg_main', asset('hero/hero-main.webp')),
    ])

    <section class="bg-white py-16 md:py-24">
        <div class="mx-auto max-w-6xl px-4">
            @include('site.partials.scope-filter', ['routeName' => 'site.events', 'scope' => $scope])

            <h2 class="mt-10 font-display text-3xl font-extrabold text-ink md:text-4xl">{{ __('site.events.upcoming') }}</h2>
            @if($upcoming->isNotEmpty())
                <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($upcoming as $event)
                        @include('site.partials.event-card', ['event' => $event])
                    @endforeach
                </div>
            @else
                <p class="mt-6 rounded-3xl bg-brand-50 p-8 text-slate-600">{{ __('site.home.no_events') }}</p>
            @endif
        </div>
    </section>

    @if($past->isNotEmpty())
        <section class="bg-brand-50 py-16 md:py-24">
            <div class="mx-auto max-w-6xl px-4">
                <h2 class="font-display text-3xl font-extrabold text-ink md:text-4xl">{{ __('site.events.past') }}</h2>
                <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($past as $event)
                        @include('site.partials.event-card', ['event' => $event])
                    @endforeach
                </div>
                <div class="mt-8">{{ $past->links() }}</div>
            </div>
        </section>
    @endif
@endsection
