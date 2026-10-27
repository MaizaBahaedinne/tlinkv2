@extends('site.layouts.site')

@section('title', __('site.projects.title'))
@section('description', __('site.projects.intro'))

@section('content')
    @include('site.partials.page-header', ['title' => __('site.projects.title'), 'intro' => __('site.projects.intro')])

    <section class="mx-auto mt-10 max-w-6xl px-4">
        @if($projects->isEmpty())
            <p class="site-card p-6 text-slate-600">{{ __('site.projects.empty') }}</p>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($projects as $project)
                    @include('site.partials.project-card', ['project' => $project])
                @endforeach
            </div>
        @endif
    </section>
@endsection
