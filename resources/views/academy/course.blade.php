@extends('layouts.base', ['title' => $course->title.' | '.$name])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        <a href="{{ route('academy.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $name }}</a>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
            @if($course->coverUrl())
                <img src="{{ $course->coverUrl() }}" alt="" class="h-48 w-full object-cover">
            @endif
            <div class="space-y-3 p-6">
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-wide">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">{{ $course->levelLabel() }}</span>
                    <span class="rounded-full px-2 py-0.5 {{ $course->isFree() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $course->priceLabel() }}</span>
                </div>
                <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $course->title }}</h1>
                @if($course->instructor_name)<p class="text-sm text-slate-500">Avec {{ $course->instructor_name }}</p>@endif
                @if($course->summary)<p class="text-slate-700">{{ $course->summary }}</p>@endif
                @if($course->description)<p class="whitespace-pre-line text-sm text-slate-600">{{ $course->description }}</p>@endif

                @if($course->badge)
                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <x-badge-medal :badge="$course->badge" size="md" :locked="! ($enrollment?->completed_at)" />
                        <p class="text-sm text-slate-700">Badge « {{ $course->badge->name }} » à la fin du cours, plus {{ $course->completion_points }} point(s) bonus.</p>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    @if($enrollment?->completed_at)
                        <a href="{{ route('academy.certificate', $enrollment->certificate_code) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-certificate mr-1" aria-hidden="true"></i> Voir mon certificat</a>
                    @endif
                    @if($canAccess)
                        <form method="post" action="{{ route('academy.courses.start', $course->slug) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">{{ $progress['done'] > 0 ? 'Reprendre le cours' : 'Commencer le cours' }}</button>
                        </form>
                    @elseif($salesEnabled)
                        <form method="post" action="{{ route('academy.courses.buy', $course->slug) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Acheter ce cours — {{ $course->priceLabel() }}</button>
                        </form>
                    @else
                        <p class="text-sm text-slate-500">Les inscriptions payantes sont momentanément fermées.</p>
                    @endif
                </div>

                @if(! $canAccess && $plans->isNotEmpty() && $salesEnabled)
                    <p class="text-xs text-slate-500">Ce cours est aussi inclus dans :
                        @foreach($plans as $plan)
                            <form method="post" action="{{ route('academy.plans.subscribe', $plan) }}" class="inline">@csrf<button type="submit" class="font-semibold text-tlink-blue hover:underline">{{ $plan->name }} ({{ $plan->priceLabel() }})</button></form>@if(! $loop->last), @endif
                        @endforeach
                    </p>
                @endif

                @if($progress['total'] > 0 && $canAccess)
                    <div class="flex items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-tlink-blue" style="width: {{ $progress['percent'] }}%"></div>
                        </div>
                        <span class="text-xs font-semibold text-slate-600">{{ $progress['done'] }} / {{ $progress['total'] }} leçon(s)</span>
                    </div>
                @endif
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-lg font-semibold text-slate-900">Programme</h2>
            @forelse($course->modules as $module)
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                    <h3 class="font-display text-base font-semibold text-slate-900">{{ $loop->iteration }}. {{ $module->title }}</h3>
                    @if($module->description)<p class="mt-1 text-sm text-slate-600">{{ $module->description }}</p>@endif
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach($module->lessons as $lesson)
                            @php $done = in_array($lesson->id, $progress['completed_ids'], true); @endphp
                            <li class="flex items-center gap-3 py-2 text-sm">
                                <i class="fa-solid {{ $done ? 'fa-circle-check text-emerald-600' : ($lesson->kind === 'video' ? 'fa-circle-play text-slate-400' : 'fa-file-lines text-slate-400') }}" aria-hidden="true"></i>
                                @if($canAccess || $lesson->is_preview)
                                    <a href="{{ route('academy.lessons.show', $lesson) }}" class="flex-1 font-medium text-slate-800 hover:text-tlink-blue">{{ $lesson->title }}</a>
                                @else
                                    <span class="flex-1 text-slate-500">{{ $lesson->title }} <i class="fa-solid fa-lock ml-1 text-xs" aria-hidden="true"></i></span>
                                @endif
                                @if($lesson->is_preview && ! $canAccess)<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Aperçu</span>@endif
                                <span class="text-xs text-slate-500">{{ $lesson->points }} pt @if($lesson->duration_minutes) · {{ $lesson->duration_minutes }} min @endif</span>
                            </li>
                        @endforeach
                        @if($quizByModule->has($module->id))
                            @include('academy._quiz-row', ['quiz' => $quizByModule[$module->id], 'isPassed' => in_array($quizByModule[$module->id]->id, $passedQuizIds, true)])
                        @endif
                    </ul>
                </article>
            @empty
                <p class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Le programme de ce cours arrive bientôt.</p>
            @endforelse
            @if($finalQuiz)
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                    <h3 class="font-display text-base font-semibold text-slate-900">Évaluation finale</h3>
                    <ul class="mt-2">
                        @include('academy._quiz-row', ['quiz' => $finalQuiz, 'isPassed' => in_array($finalQuiz->id, $passedQuizIds, true)])
                    </ul>
                </article>
            @endif
        </section>

        @if($sessions->isNotEmpty())
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Prochaines sessions en direct</h2>
                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                    @foreach($sessions as $session)
                        <li><strong>{{ $session->title }}</strong> — {{ $session->starts_at->translatedFormat('l d F à H:i') }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('academy.calendar') }}" class="mt-2 inline-block text-sm font-semibold text-tlink-blue hover:underline">Voir tout le calendrier</a>
            </section>
        @endif
    </main>
</div>
@endsection
