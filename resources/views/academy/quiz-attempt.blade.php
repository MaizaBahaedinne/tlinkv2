@extends('layouts.base', ['title' => $quiz->title.' | Quiz'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl py-6">
        <form method="post" action="{{ route('academy.attempts.submit', $attempt) }}" class="space-y-5">
            @csrf
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $quiz->title }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ count($questions) }} question(s), dans un ordre aléatoire. Score minimal pour réussir : {{ $quiz->pass_percent }} %.</p>
            </section>

            @foreach($questions as $index => $question)
                <fieldset class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:p-6">
                    <legend class="sr-only">Question {{ $index + 1 }}</legend>
                    <p class="font-display text-base font-semibold text-slate-900 sm:text-lg"><span class="mr-2 text-tlink-blue">{{ $index + 1 }}.</span>{{ $question['text'] }}</p>
                    @if($question['type'] === 'multiple')
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Plusieurs réponses possibles</p>
                    @endif
                    <div class="mt-4 grid gap-2.5">
                        @foreach($question['options'] as $option)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm transition hover:border-tlink-blue/50 hover:bg-slate-50 has-[:checked]:border-tlink-blue has-[:checked]:bg-rose-50">
                                <input type="{{ $question['type'] === 'multiple' ? 'checkbox' : 'radio' }}" name="answers[{{ $question['id'] }}][]" value="{{ $option['id'] }}" class="mt-0.5 h-4 w-4 shrink-0 accent-[#d00031]">
                                <span>{{ $option['text'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('academy.quizzes.show', $quiz) }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">&larr; Quitter (reprise possible)</a>
                <button type="submit" class="rounded-xl bg-tlink-blue px-8 py-3 text-sm font-semibold text-white shadow-soft hover:opacity-90">Envoyer mes réponses</button>
            </div>
        </form>
    </main>
</div>
@endsection
