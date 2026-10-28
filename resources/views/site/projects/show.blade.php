@extends('site.layouts.site')

@section('title', $project->text('name'))
@section('description', $project->text('summary') !== '' ? $project->text('summary') : \Illuminate\Support\Str::limit(strip_tags($project->text('body')), 160))

@php
    $figures = $project->figures();
    $cover = $project->coverUrl();
    $body = $project->text('body');
    $logo = $project->logoUrl();
@endphp

@section('content')
    <section class="relative overflow-hidden bg-gradient-to-br from-brand-900 to-brand-800 text-white">
        @if($cover)
            <img src="{{ $cover }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30" loading="eager" decoding="async">
        @endif
        <div class="relative mx-auto max-w-6xl px-4 py-14 md:py-20">
            <a href="{{ route('site.projects') }}" class="text-sm font-bold text-white/80 hover:text-white">← {{ __('site.projects.title') }}</a>
            @if($logo)
                <div class="mt-5 inline-flex rounded-2xl bg-white p-3 shadow-lg">
                    <img src="{{ $logo }}" alt="{{ $project->text('name') }}" class="h-16 w-auto max-w-56 object-contain md:h-20" loading="eager" decoding="async">
                </div>
            @endif
            <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-bold">
                <span class="rounded-full bg-white/15 px-3 py-1">{{ __('site.projects.status_'.$project->status) }}</span>
                @if($project->started_year)
                    <span class="rounded-full bg-white/15 px-3 py-1">{{ __('site.projects.since', ['year' => $project->started_year]) }}</span>
                @endif
            </div>
            <h1 class="mt-4 max-w-3xl font-display text-3xl font-bold md:text-5xl">{{ $project->text('name') }}</h1>
            @if($project->text('summary') !== '')
                <p class="mt-4 max-w-2xl text-lg text-slate-200">{{ $project->text('summary') }}</p>
            @endif
        </div>
    </section>

    @if($figures !== [])
        <section class="relative z-10 mx-auto -mt-8 max-w-6xl px-4" aria-labelledby="project-figures">
            <h2 id="project-figures" class="sr-only">{{ __('site.projects.figures') }}</h2>
            <dl class="site-card grid grid-cols-2 gap-6 p-6 text-center md:grid-cols-4">
                @foreach($figures as $figure)
                    <div>
                        <dd class="font-display text-3xl font-bold text-brand-800 md:text-4xl">{{ $figure['value'] }}</dd>
                        <dt class="mt-1 text-sm font-semibold text-slate-600">{{ $figure['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    <article class="mx-auto mt-12 max-w-3xl px-4">
        @if($body !== '')
            <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.projects.about') }}</h2>
            <div class="site-prose mt-4 text-slate-700">
                @foreach(preg_split('/\R{2,}/u', $body) as $paragraph)
                    <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                @endforeach
            </div>
        @endif

        @if($url = $project->safeUrl())
            <p class="mt-8">
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-block rounded-xl bg-brand-800 px-6 py-3 font-bold text-white hover:bg-brand-900">{{ __('site.projects.visit') }} ↗</a>
            </p>
        @endif
    </article>

    @if($team->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4" aria-labelledby="project-team">
            <h2 id="project-team" class="font-display text-2xl font-bold text-brand-900">{{ __('site.projects.team') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($team as $node)
                    @foreach($node->assignments as $assignment)
                        @php($person = $assignment->person)
                        @continue(! $person)
                        @php($photo = $person->photoUrl())
                        <div class="site-card flex items-center gap-4 p-4">
                            @if($photo)
                                <img src="{{ $photo }}" alt="" loading="lazy" class="h-16 w-16 shrink-0 rounded-full object-cover">
                            @else
                                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-brand-100 font-display text-xl font-bold text-brand-800">{{ mb_strtoupper(mb_substr($person->displayName(), 0, 1)) }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-display font-bold text-brand-900">{{ $person->displayName() }}</p>
                                <p class="text-sm text-slate-600">{{ $node->title }}</p>
                                @if($node->subtitle)
                                    <p class="text-xs text-slate-500">{{ $node->subtitle }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    @if($upcomingEvents->isNotEmpty() || $pastEvents->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4" aria-labelledby="project-events">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="project-events" class="font-display text-2xl font-bold text-brand-900">{{ __('site.projects.events') }}</h2>
                <a href="{{ route('site.events', ['scope' => $project->scope]) }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.projects.all_events') }} →</a>
            </div>
            @if($upcomingEvents->isNotEmpty())
                <h3 class="mt-5 text-sm font-bold uppercase tracking-wide text-slate-500">{{ __('site.events.upcoming') }}</h3>
                <div class="mt-3 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($upcomingEvents as $event)
                        @include('site.partials.event-card', ['event' => $event])
                    @endforeach
                </div>
            @endif
            @if($pastEvents->isNotEmpty())
                <h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-slate-500">{{ __('site.events.past') }}</h3>
                <div class="mt-3 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($pastEvents as $event)
                        @include('site.partials.event-card', ['event' => $event])
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($news->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4" aria-labelledby="project-news">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="project-news" class="font-display text-2xl font-bold text-brand-900">{{ __('site.projects.news') }}</h2>
                <a href="{{ route('site.news', ['scope' => $project->scope]) }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.projects.all_news') }} →</a>
            </div>
            <div class="mt-5 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($news as $item)
                    @include('site.partials.news-card', ['item' => $item])
                @endforeach
            </div>
        </section>
    @endif

    @if($others->isNotEmpty())
        <section class="mx-auto mt-16 max-w-6xl px-4">
            <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.projects.others') }}</h2>
            <div class="mt-5 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($others as $other)
                    @include('site.partials.project-card', ['project' => $other])
                @endforeach
            </div>
        </section>
    @endif
@endsection
