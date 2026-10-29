@extends('layouts.base', ['title' => 'Résultat | '.$quiz->title])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <section class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-soft">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $quiz->title }}</p>
            <p class="font-display text-5xl font-semibold {{ $passed ? 'text-emerald-600' : 'text-tlink-blue' }}">{{ $attempt->score_percent }} %</p>
            <p class="text-lg font-semibold text-slate-900">{{ $passed ? 'Quiz réussi, bravo !' : 'Quiz non réussi' }}</p>
            <p class="text-sm text-slate-600">Niveau évalué : <strong>{{ $level }}</strong> · Score minimal : {{ $quiz->pass_percent }} %</p>

            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                @if($certificateCode)
                    <a href="{{ route('academy.certificate', $certificateCode) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-certificate mr-1" aria-hidden="true"></i> Voir mon certificat</a>
                @endif
                @unless($passed)
                    <form method="post" action="{{ route('academy.quizzes.start', $quiz) }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Réessayer</button>
                    </form>
                @endunless
                <a href="{{ route('academy.courses.show', $course->slug) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour au cours</a>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-lg font-semibold text-slate-900">Correction</h2>
            @foreach($review as $index => $question)
                <article class="rounded-2xl border bg-white p-5 shadow-soft {{ $question['ok'] ? 'border-emerald-200' : 'border-rose-200' }}">
                    <p class="font-display text-base font-semibold text-slate-900"><span class="mr-2 {{ $question['ok'] ? 'text-emerald-600' : 'text-tlink-blue' }}">{{ $index + 1 }}.</span>{{ $question['text'] }}</p>
                    @unless($question['answered'])<p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Sans réponse</p>@endunless
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach($question['options'] as $option)
                            <li class="rounded-xl border px-3 py-2 {{ match($option['state']) {
                                'right' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
                                'wrong' => 'border-rose-300 bg-rose-50 text-rose-900',
                                'missed' => 'border-amber-300 bg-amber-50 text-amber-900',
                                default => 'border-slate-200 text-slate-600',
                            } }}">
                                {{ $option['text'] }}
                                @if($option['state'] === 'right')<span class="ml-1 text-xs font-bold">— bonne réponse</span>@endif
                                @if($option['state'] === 'wrong')<span class="ml-1 text-xs font-bold">— incorrect</span>@endif
                                @if($option['state'] === 'missed')<span class="ml-1 text-xs font-bold">— à cocher</span>@endif
                            </li>
                        @endforeach
                    </ul>
                    @if($question['explanation'])<p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $question['explanation'] }}</p>@endif
                </article>
            @endforeach
        </section>
    </main>
</div>
@endsection
