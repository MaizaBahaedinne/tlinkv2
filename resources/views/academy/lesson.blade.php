@extends('layouts.base', ['title' => $lesson->title.' | '.$course->title])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <a href="{{ route('academy.courses.show', $course->slug) }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $course->title }}</a>

        <article class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $lesson->module->title }}</p>
            <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $lesson->title }}</h1>

            @if($lesson->kind === 'video' && $lesson->embedUrl())
                <div class="aspect-video overflow-hidden rounded-2xl bg-black">
                    <iframe src="{{ $lesson->embedUrl() }}" title="{{ $lesson->title }}" class="h-full w-full" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; encrypted-media; picture-in-picture"></iframe>
                </div>
            @endif

            @if($lesson->content)
                <div class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $lesson->content }}</div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p class="text-xs text-slate-500">{{ $lesson->points }} point(s) @if($lesson->duration_minutes) · {{ $lesson->duration_minutes }} min @endif · progression {{ $progress['done'] }} / {{ $progress['total'] }}</p>
                @if($completed)
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-emerald-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i> Leçon terminée</span>
                @elseif($canComplete)
                    <form method="post" action="{{ route('academy.lessons.complete', $lesson) }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Marquer comme terminée</button>
                    </form>
                @else
                    <p class="text-xs text-slate-500">Débloquez le cours pour valider cette leçon.</p>
                @endif
            </div>
        </article>

        <nav class="flex justify-between gap-3 text-sm font-semibold" aria-label="Navigation entre les leçons">
            @if($previous)
                <a href="{{ route('academy.lessons.show', $previous) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $previous->title }}</a>
            @else
                <span></span>
            @endif
            @if($next)
                <a href="{{ route('academy.lessons.show', $next) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-slate-700 hover:bg-slate-50">{{ $next->title }} <i class="fa-solid fa-arrow-right ml-1" aria-hidden="true"></i></a>
            @endif
        </nav>
    </main>
</div>
@endsection
