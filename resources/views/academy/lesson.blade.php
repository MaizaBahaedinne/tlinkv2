@extends('layouts.base', ['title' => $lesson->title.' | '.$course->title])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container py-6">
        <a href="{{ route('academy.courses.show', $course->slug) }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $course->title }}</a>

        <div class="mt-4 grid gap-5 lg:grid-cols-3 lg:items-start">
        <div class="space-y-5 lg:col-span-2">
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
        </div>

        <aside class="space-y-4 lg:sticky lg:top-4" aria-label="Sommaire du cours">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                <h2 class="font-display text-base font-semibold text-slate-900">Votre avancement</h2>
                <div class="mt-3 flex items-baseline justify-between text-sm">
                    <span class="text-slate-600">{{ $progress['done'] }} / {{ $progress['total'] }} leçon(s)</span>
                    <span class="font-display text-xl font-semibold text-tlink-blue">{{ $progress['percent'] }} %</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full bg-tlink-blue" style="width: {{ $progress['percent'] }}%"></div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                <h2 class="font-display text-base font-semibold text-slate-900">Modules et leçons</h2>
                <div class="mt-3 space-y-4">
                    @foreach($course->modules as $module)
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $loop->iteration }}. {{ $module->title }}</h3>
                            <ul class="mt-1 divide-y divide-slate-100">
                                @foreach($module->lessons as $item)
                                    @php $itemDone = in_array($item->id, $progress['completed_ids'], true); @endphp
                                    <li class="flex items-center gap-2 py-2 text-sm {{ $item->id === $lesson->id ? 'font-semibold text-tlink-blue' : '' }}">
                                        <i class="fa-solid {{ $itemDone ? 'fa-circle-check text-emerald-600' : ($item->id === $lesson->id ? 'fa-circle-play text-tlink-blue' : 'fa-circle text-slate-300') }}" aria-hidden="true"></i>
                                        @if($canComplete || $item->is_preview)
                                            <a href="{{ route('academy.lessons.show', $item) }}" class="flex-1 hover:text-tlink-blue" @if($item->id === $lesson->id) aria-current="page" @endif>{{ $item->title }}</a>
                                        @else
                                            <span class="flex-1 text-slate-500">{{ $item->title }} <i class="fa-solid fa-lock ml-1 text-xs" aria-hidden="true"></i></span>
                                        @endif
                                    </li>
                                @endforeach
                                @if($quizByModule->has($module->id))
                                    @include('academy._quiz-row', ['quiz' => $quizByModule[$module->id], 'isPassed' => in_array($quizByModule[$module->id]->id, $passedQuizIds, true), 'canAccess' => $canComplete])
                                @endif
                            </ul>
                        </div>
                    @endforeach
                    @if($finalQuiz)
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Évaluation finale</h3>
                            <ul class="mt-1">
                                @include('academy._quiz-row', ['quiz' => $finalQuiz, 'isPassed' => in_array($finalQuiz->id, $passedQuizIds, true), 'canAccess' => $canComplete])
                            </ul>
                        </div>
                    @endif
                </div>
            </section>
        </aside>
        </div>
    </main>
</div>
@endsection
