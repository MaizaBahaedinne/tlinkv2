@extends('layouts.base', ['title' => $quiz->title.' | Quiz'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <a href="{{ route('academy.courses.show', $quiz->course->slug) }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $quiz->course->title }}</a>

        <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $quiz->module ? 'Quiz du module : '.$quiz->module->title : 'Quiz final du cours' }}</p>
            <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $quiz->title }}</h1>
            @if($quiz->intro)<p class="whitespace-pre-line text-sm text-slate-600">{{ $quiz->intro }}</p>@endif

            <ul class="space-y-1 text-sm text-slate-700">
                <li><i class="fa-solid fa-shuffle mr-2 text-slate-400" aria-hidden="true"></i>{{ $quiz->drawSize($questionCount) }} question(s) dans un ordre aléatoire, réponses mélangées à chaque tentative.</li>
                <li><i class="fa-solid fa-bullseye mr-2 text-slate-400" aria-hidden="true"></i>Score minimal pour réussir : {{ $quiz->pass_percent }} %. Votre niveau est évalué d’après votre score.</li>
                <li><i class="fa-solid fa-rotate-right mr-2 text-slate-400" aria-hidden="true"></i>Vous pouvez recommencer autant de fois que nécessaire.</li>
            </ul>

            @if($passed)
                <p class="rounded-2xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i> Quiz réussi — meilleur score : {{ $best }} %.</p>
            @elseif($best !== null)
                <p class="rounded-2xl bg-amber-50 p-3 text-sm text-amber-900">Meilleur score pour l’instant : {{ $best }} %. Il faut {{ $quiz->pass_percent }} % pour réussir.</p>
            @endif

            @if($questionCount > 0)
                <form method="post" action="{{ route('academy.quizzes.start', $quiz) }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">{{ $hasOpen ? 'Reprendre le quiz' : ($attempts->isNotEmpty() ? 'Recommencer le quiz' : 'Commencer le quiz') }}</button>
                </form>
            @else
                <p class="text-sm text-slate-500">Ce quiz n’a pas encore de questions.</p>
            @endif
        </section>

        @if($attempts->isNotEmpty())
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Vos dernières tentatives</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @foreach($attempts as $past)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span>{{ $past->submitted_at?->translatedFormat('d M Y H:i') ?? $past->started_at->translatedFormat('d M Y H:i') }}</span>
                            <span class="font-semibold {{ $past->status === 'passed' ? 'text-emerald-700' : 'text-slate-600' }}">{{ $past->score_percent }} % — {{ $past->status === 'passed' ? 'réussi' : 'non réussi' }}</span>
                            <a href="{{ route('academy.attempts.show', $past) }}" class="font-semibold text-tlink-blue hover:underline">Correction</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</div>
@endsection
