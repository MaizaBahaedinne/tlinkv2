@extends('site.layouts.site')

@section('title', __('site.events.title'))

@section('content')
    @include('site.partials.page-header', ['title' => __('site.events.title'), 'intro' => __('site.events.intro')])

    <section class="mx-auto mt-10 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.events.upcoming') }}</h2>
        @if($upcoming->isNotEmpty())
            <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($upcoming as $event)
                    @include('site.partials.event-card', ['event' => $event])
                @endforeach
            </div>
        @else
            <p class="mt-5 text-slate-500">{{ __('site.home.no_events') }}</p>
        @endif
    </section>

    @if($past->isNotEmpty())
        <section class="mx-auto mt-14 max-w-6xl px-4">
            <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.events.past') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($past as $event)
                    @include('site.partials.event-card', ['event' => $event])
                @endforeach
            </div>
            <div class="mt-8">{{ $past->links() }}</div>
        </section>
    @endif
@endsection
