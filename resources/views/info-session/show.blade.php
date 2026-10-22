@extends('layouts.base', ['title' => $settings['title'].' | T.Link'])

@section('content')
@php
    $result = session('quiz_result') ? $last : null;
    $statusLabels = \App\Models\QuizAttempt::STATUSES;
@endphp
<div class="min-h-screen px-4 py-8">
    <div class="mx-auto w-full max-w-4xl">
        <header class="mb-6 flex items-center justify-between">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-tlink-blue font-display text-lg font-bold text-white shadow-soft">T</span>
                <span class="font-display text-xl font-semibold text-tlink-blue">T.Link</span>
            </a>
            <a href="{{ route('home') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Retour au fil</a>
        </header>

        @if($validated)
            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-900" role="status">
                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-600 text-white">&#10003;</span>
                <p class="text-sm font-medium">Ton compte est validé{{ $validatedAt ? ' depuis le '.$validatedAt->format('d/m/Y') : '' }}. Tu peux revoir l'info session quand tu veux.</p>
            </div>
        @endif

        @if($result)
            @if($result->status === 'passed')
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-900" role="status">
                    <p class="font-display text-lg font-semibold">Bravo, quiz réussi : {{ $result->score }} / {{ $result->total }}</p>
                    <p class="mt-1 text-sm">Ton compte est maintenant validé.</p>
                </div>
            @elseif($result->status === 'expired')
                <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900" role="alert">
                    <p class="font-display text-lg font-semibold">Le temps est écoulé</p>
                    <p class="mt-1 text-sm">Ta tentative n'a pas pu être corrigée. Tu peux revoir la vidéo puis relancer le quiz.</p>
                </div>
            @else
                <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900" role="alert">
                    <p class="font-display text-lg font-semibold">Quiz non validé : {{ $result->score }} / {{ $result->total }}</p>
                    <p class="mt-1 text-sm">Il faut {{ $settings['pass_percent'] }} % de bonnes réponses. Revois la vidéo, puis retente le quiz : les questions et les réponses changent d'ordre à chaque tentative.</p>
                </div>
            @endif
        @endif

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
            <div class="px-6 pt-6 sm:px-8">
                <h1 class="font-display text-2xl font-semibold text-slate-900 sm:text-3xl">{{ $settings['title'] }}</h1>
                <p class="mt-1 text-sm text-slate-500">Regarde la vidéo jusqu'au bout pour débloquer le quiz ({{ $settings['duration_minutes'] }} min pour répondre).</p>
            </div>

            <div class="mt-5 px-6 sm:px-8">
                @if($videoId)
                    <div class="relative aspect-video w-full overflow-hidden rounded-2xl bg-slate-900" id="video-box">
                        <div id="ytplayer" class="absolute inset-0 h-full w-full"></div>
                        <button type="button" id="video-play" class="absolute inset-0 z-10 grid place-items-center bg-slate-900/55 text-white backdrop-blur-[1px] transition hover:bg-slate-900/45" aria-label="Lancer la vidéo">
                            <span class="flex flex-col items-center gap-3">
                                <span class="grid h-20 w-20 place-items-center rounded-full bg-tlink-blue shadow-lg">
                                    <svg viewBox="0 0 24 24" class="ml-1 h-9 w-9" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                                <span class="text-sm font-semibold" id="video-play-label">Lancer la vidéo</span>
                            </span>
                        </button>
                    </div>
                    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <div id="video-progress" class="h-full w-0 rounded-full bg-tlink-blue transition-[width]"></div>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">La vidéo n'est pas encore configurée : tu peux passer directement au quiz.</div>
                @endif
            </div>

            @if(trim($settings['intro']) !== '')
                <div class="mt-5 whitespace-pre-line px-6 text-sm leading-relaxed text-slate-600 sm:px-8">{{ $settings['intro'] }}</div>
            @endif

            <form method="post" action="{{ route('info-session.start') }}" class="mt-6 flex flex-wrap items-center gap-4 border-t border-slate-100 bg-slate-50/70 px-6 py-5 sm:px-8">
                @csrf
                <button type="submit" id="start-quiz" @if($videoId && ! $open) disabled @endif class="rounded-xl bg-tlink-blue px-6 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-tlink-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                    {{ $open ? 'Reprendre le quiz' : ($validated ? 'Refaire le quiz' : 'Lancer le quiz') }}
                </button>
                <p id="quiz-hint" class="text-sm text-slate-500">
                    @if($videoId && ! $open)
                        Le quiz se débloque à la fin de la vidéo.
                    @elseif($open)
                        Une tentative est en cours : tu peux la reprendre.
                    @else
                        Prêt ? Tu as {{ $settings['duration_minutes'] }} minutes.
                    @endif
                </p>
            </form>
        </section>

        @if($review !== [])
            <section class="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft" id="correction">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6 sm:px-8">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Ta correction</h2>
                        <p class="mt-1 text-sm text-slate-500">Dernière tentative, {{ $last->submitted_at?->format('d/m/Y à H:i') }}</p>
                    </div>
                    <span class="rounded-full px-4 py-1.5 text-sm font-semibold {{ $last->status === 'passed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $last->score }} / {{ $last->total }}</span>
                </div>

                <ol class="mt-5 space-y-4 px-6 pb-6 sm:px-8">
                    @foreach($review as $index => $item)
                        <li class="rounded-2xl border p-4 sm:p-5 {{ $item['ok'] ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50' }}">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-display text-base font-semibold text-slate-900"><span class="mr-2 text-tlink-blue">{{ $index + 1 }}.</span>{{ $item['text'] }}</p>
                                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $item['ok'] ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' }}">{{ $item['ok'] ? 'VRAI' : 'FAUX' }}</span>
                            </div>
                            @if($item['type'] === 'multiple')
                                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Plusieurs réponses possibles</p>
                            @endif
                            @unless($item['answered'])
                                <p class="mt-2 text-sm font-medium text-rose-700">Tu n'as pas répondu à cette question.</p>
                            @endunless

                            <ul class="mt-3 grid gap-2">
                                @foreach($item['options'] as $option)
                                    @php
                                        $style = match ($option['state']) {
                                            'right' => 'border-emerald-300 bg-emerald-100 text-emerald-900',
                                            'wrong' => 'border-rose-300 bg-rose-100 text-rose-900',
                                            'missed' => 'border-dashed border-emerald-400 bg-white text-emerald-800',
                                            default => 'border-slate-200 bg-white text-slate-500',
                                        };
                                        $tag = match ($option['state']) {
                                            'right' => 'Ta réponse : juste',
                                            'wrong' => 'Ta réponse : fausse',
                                            'missed' => 'Bonne réponse',
                                            default => null,
                                        };
                                    @endphp
                                    <li class="flex items-start justify-between gap-3 rounded-xl border px-4 py-2.5 text-sm {{ $style }}">
                                        <span>{{ $option['text'] }}</span>
                                        @if($tag)<span class="shrink-0 text-xs font-bold uppercase tracking-wide">{{ $tag }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</div>

@if($videoId)
<script>
    (function () {
        var videoId = @json($videoId);
        var button = document.getElementById('start-quiz');
        var hint = document.getElementById('quiz-hint');
        var overlay = document.getElementById('video-play');
        var label = document.getElementById('video-play-label');
        var bar = document.getElementById('video-progress');
        var player = null;
        var ticker = null;

        function unlock(message) {
            button.disabled = false;
            hint.textContent = message || 'Vidéo terminée : tu peux lancer le quiz.';
        }

        function track() {
            if (!player || !player.getDuration) return;
            var total = player.getDuration();
            if (total > 0) bar.style.width = Math.min(100, (player.getCurrentTime() / total) * 100) + '%';
        }

        window.onYouTubeIframeAPIReady = function () {
            player = new YT.Player('ytplayer', {
                videoId: videoId,
                host: 'https://www.youtube-nocookie.com',
                playerVars: { controls: 0, disablekb: 1, fs: 0, rel: 0, modestbranding: 1, playsinline: 1, cc_load_policy: 1 },
                events: {
                    onStateChange: function (event) {
                        if (event.data === YT.PlayerState.PLAYING) {
                            overlay.hidden = true;
                            clearInterval(ticker);
                            ticker = setInterval(track, 500);
                        } else if (event.data === YT.PlayerState.PAUSED) {
                            label.textContent = 'Reprendre la vidéo';
                            overlay.hidden = false;
                        } else if (event.data === YT.PlayerState.ENDED) {
                            clearInterval(ticker);
                            bar.style.width = '100%';
                            overlay.hidden = true;
                            unlock();
                        }
                    }
                }
            });
        };

        overlay.addEventListener('click', function () {
            if (player && player.playVideo) player.playVideo();
        });

        // Si YouTube est inaccessible (réseau, bloqueur), on ne bloque pas le membre indéfiniment.
        setTimeout(function () {
            if (!player || !player.playVideo) {
                overlay.hidden = true;
                unlock('La vidéo n\'a pas pu se charger : tu peux lancer le quiz.');
            }
        }, 12000);

        var script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(script);
    })();
</script>
@endif
@endsection
