@extends('site.layouts.site')

@section('title', $project->text('name'))
@section('description', $project->text('summary') !== '' ? $project->text('summary') : \Illuminate\Support\Str::limit(strip_tags($project->text('body')), 160))

@php
    $figures = $project->figures();
    $cover = $project->coverUrl();
    $body = $project->text('body');
@endphp

@section('content')
    <section class="relative overflow-hidden bg-gradient-to-br from-brand-900 to-brand-800 text-white">
        @if($cover)
            <img src="{{ $cover }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30" loading="eager" decoding="async">
        @endif
        <div class="relative mx-auto max-w-6xl px-4 py-14 md:py-20">
            <a href="{{ route('site.projects') }}" class="text-sm font-bold text-white/80 hover:text-white">← {{ __('site.projects.title') }}</a>
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
