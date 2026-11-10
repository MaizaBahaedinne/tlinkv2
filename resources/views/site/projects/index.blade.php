@extends('site.layouts.site')

@section('title', __('site.projects.title'))
@section('description', __('site.projects.intro'))
@section('flush_footer', '1')

@section('content')
    @include('site.partials.page-header', [
        'title' => __('site.projects.title'),
        'intro' => __('site.projects.intro'),
        'kicker' => __('site.projects.kicker'),
        'image' => $site->heroImage('hero_bg_mark_b', asset('hero/hero-mark-b.webp')),
    ])

    <section class="bg-white py-16 md:py-24">
        <div class="mx-auto max-w-6xl px-4">
            @if($projects->isEmpty())
                <p class="site-card p-8 text-slate-600">{{ __('site.projects.empty') }}</p>
            @else
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($projects as $project)
                        @include('site.partials.project-card', ['project' => $project])
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @include('site.partials.service-cards')
@endsection
