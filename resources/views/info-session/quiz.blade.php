@extends('layouts.base', ['title' => $title.' | Quiz | T.Link'])

@section('content')
<div class="min-h-screen px-4 pb-16">
    {{-- Compte à rebours : le serveur reste maître du temps (échéance enregistrée à l'ouverture du quiz). --}}
    <div class="sticky top-0 z-30 -mx-4 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
        <div class="flex w-full items-center justify-between gap-4">
            <p class="font-display text-lg font-semibold text-slate-900">{{ $title }}</p>
            <p class="rounded-full border border-slate-200 bg-white px-4 py-1.5 text-sm font-semibold text-slate-700" role="timer" aria-live="off">
                Il te reste <span id="clock" class="tabular-nums text-tlink-blue">--:--</span>
            </p>
        </div>
    </div>

    <form method="post" action="{{ route('info-session.submit', $attempt) }}" id="quiz-form" class="mt-6 w-full space-y-5">
        @csrf
        <p class="text-sm text-slate-600">Tu as {{ $minutes }} minutes pour répondre. Les questions et les réponses sont dans un ordre aléatoire.</p>

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
            <a href="{{ route('info-session.show') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">&larr; Revoir la vidéo</a>
            <button type="submit" id="send" class="rounded-xl bg-tlink-blue px-8 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-tlink-blue-700 disabled:opacity-50">Envoyer mes réponses</button>
        </div>
    </form>
</div>

<script>
    (function () {
        var remaining = {{ $remaining }};
        var clock = document.getElementById('clock');
        var form = document.getElementById('quiz-form');
        var send = document.getElementById('send');
        var sent = false;

        function submit() {
            if (sent) return;
            sent = true;
            send.disabled = true;
            form.submit();
        }

        function draw() {
            var minutes = Math.floor(remaining / 60);
            var seconds = remaining % 60;
            clock.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            if (remaining <= 60) clock.classList.add('animate-pulse');
        }

        form.addEventListener('submit', function (event) {
            if (sent) { event.preventDefault(); return; }
            sent = true;
            send.disabled = true;
        });

        draw();
        var timer = setInterval(function () {
            remaining -= 1;
            if (remaining <= 0) {
                remaining = 0;
                draw();
                clearInterval(timer);
                submit();
                return;
            }
            draw();
        }, 1000);

        if (remaining <= 0) submit();
    })();
</script>
@endsection
