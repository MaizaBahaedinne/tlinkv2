@extends('site.layouts.site')

@section('title', $name)
@section('description', __('site.showcase.academy.intro'))

@section('content')
    @include('site.partials.page-header', ['title' => $name, 'intro' => __('site.showcase.academy.intro')])

    <section class="mx-auto mt-10 max-w-6xl px-4">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('academy.index') }}" class="rounded-xl bg-brand-600 px-6 py-3 font-bold text-white hover:bg-brand-800">{{ __('site.showcase.academy.cta') }}</a>
            @guest
                <a href="{{ $loginUrl }}" class="rounded-xl border border-slate-300 bg-white px-6 py-3 font-bold text-slate-700 hover:bg-slate-100">{{ __('site.showcase.login') }}</a>
                <a href="{{ $registerUrl }}" class="rounded-xl border border-slate-300 bg-white px-6 py-3 font-bold text-slate-700 hover:bg-slate-100">{{ __('site.showcase.register') }}</a>
            @endguest
        </div>
    </section>

    <section class="mx-auto mt-10 max-w-6xl px-4">
        <div class="grid gap-5 md:grid-cols-3">
            @foreach([1, 2, 3] as $i)
                <article class="site-card p-6">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-800 font-display text-sm font-bold text-white">0{{ $i }}</span>
                    <h2 class="mt-4 font-display text-lg font-bold text-brand-800">{{ __('site.showcase.academy.point_'.$i.'_title') }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ __('site.showcase.academy.point_'.$i.'_text') }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.showcase.academy.catalog') }}</h2>
        @if($courses->isEmpty())
            <p class="site-card mt-4 p-6 text-slate-600">{{ __('site.showcase.academy.empty') }}</p>
        @else
            <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $course)
                    <a href="{{ route('academy.courses.show', $course->slug) }}" class="site-card group flex h-full flex-col overflow-hidden transition hover:-translate-y-1 hover:shadow-lg">
                        @if($course->coverUrl())
                            <img src="{{ $course->coverUrl() }}" alt="" loading="lazy" class="h-40 w-full object-cover">
                        @else
                            <div class="flex h-40 items-center justify-center bg-gradient-to-br from-brand-900 to-brand-800 font-display text-4xl font-bold text-white/80" aria-hidden="true">{{ mb_strtoupper(mb_substr($course->title, 0, 1)) }}</div>
                        @endif
                        <div class="flex flex-1 flex-col gap-2 p-5">
                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-wide">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">{{ $course->levelLabel() }}</span>
                                <span class="rounded-full bg-brand-100 px-2 py-0.5 text-brand-800">{{ $course->priceLabel() }}</span>
                            </div>
                            <h3 class="font-display text-lg font-bold text-brand-900 group-hover:text-brand-600">{{ $course->title }}</h3>
                            @if($course->summary)<p class="text-sm leading-6 text-slate-600">{{ $course->summary }}</p>@endif
                            <p class="mt-auto pt-2 text-xs text-slate-500">{{ __('site.showcase.academy.counts', ['modules' => $course->modules_count, 'lessons' => $course->lessons_count]) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if($plans->isNotEmpty())
        <section class="mx-auto mt-12 max-w-6xl px-4">
            <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.showcase.academy.plans') }}</h2>
            <div class="mt-4 grid gap-5 md:grid-cols-3">
                @foreach($plans as $plan)
                    <article class="site-card p-6">
                        <h3 class="font-display text-lg font-bold text-brand-800">{{ $plan->name }}</h3>
                        <p class="mt-1 text-sm font-bold text-brand-600">{{ $plan->priceLabel() }} · {{ $plan->durationLabel() }}</p>
                        @if($plan->description)<p class="mt-2 text-sm leading-6 text-slate-600">{{ $plan->description }}</p>@endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
