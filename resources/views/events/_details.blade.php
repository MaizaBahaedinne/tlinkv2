{{-- Programme, intervenants, sponsors et partenaires d'un événement. Partagé entre la page membre (interactive) et la page publique. --}}
@php
    $interactive = $interactive ?? false;
    $registeredSessions = $registeredSessions ?? [];
    $sessions = $sessions ?? $event->sessions()->with('speakers')->get();
    $speakers = $speakers ?? $event->speakers()->get();
    $levels = $levels ?? $event->sponsorLevels()->with('sponsors')->get();
    $levels = $levels->filter(fn ($level) => $level->sponsors->isNotEmpty());
    $days = $sessions->groupBy(fn ($session) => $session->starts_at->toDateString());
    $canJoinSessions = $interactive && ($registration ?? null) !== null && in_array($registration->status, ['confirmed', 'pending'], true);
@endphp

@if($sessions->isNotEmpty())
    <section id="programme" class="mt-10">
        <h2 class="font-display text-2xl font-bold text-slate-900">Programme</h2>
        @foreach($days as $day => $daySessions)
            <h3 class="mt-5 text-sm font-bold uppercase tracking-wide text-slate-500">{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l d F Y') }}</h3>
            <ol class="mt-3 space-y-3">
                @foreach($daySessions as $session)
                    @php
                        $session->setRelation('event', $event);
                        $joined = in_array($session->id, $registeredSessions, true);
                        $remaining = $session->remaining();
                    @endphp
                    <li class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    {{ $session->starts_at->format('H:i') }}{{ $session->ends_at ? ' – '.$session->ends_at->format('H:i') : '' }} · {{ $session->typeLabel() }}
                                </p>
                                <h4 class="mt-1 text-base font-semibold text-slate-900">{{ $session->title }}</h4>
                                @if($venue = $session->venueLabel())
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ $venue }}@if($session->venue_mode === 'external') <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">Lieu externe</span>@endif
                                    </p>
                                @endif
                                @if($session->description)
                                    <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $session->description }}</p>
                                @endif
                                @if($session->speakers->isNotEmpty())
                                    <p class="mt-2 text-sm text-slate-600">
                                        @foreach($session->speakers as $speaker)
                                            <span class="font-semibold text-slate-800">{{ $speaker->name }}</span> <span class="text-xs">({{ $speaker->kindLabel() }})</span>{{ ! $loop->last ? ' · ' : '' }}
                                        @endforeach
                                    </p>
                                @endif
                            </div>

                            @if($session->requires_registration)
                                <div class="shrink-0 text-right text-sm">
                                    <span class="block text-xs font-semibold text-slate-500">Inscription requise{{ $remaining !== null ? ' · '.$remaining.' place(s)' : '' }}</span>
                                    @if($canJoinSessions)
                                        @if($joined)
                                            <form method="post" action="{{ route('member.events.sessions.cancel', [$event, $session]) }}" class="mt-2">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Inscrit(e) — annuler</button>
                                            </form>
                                        @elseif($remaining === 0)
                                            <span class="mt-2 inline-block text-xs font-semibold text-rose-600">Complet</span>
                                        @else
                                            <form method="post" action="{{ route('member.events.sessions.register', [$event, $session]) }}" class="mt-2">
                                                @csrf
                                                <button type="submit" class="rounded-xl bg-tlink-blue px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90">S’inscrire à cette étape</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endforeach
    </section>
@endif

@if($speakers->isNotEmpty())
    <section id="intervenants" class="mt-10">
        <h2 class="font-display text-2xl font-bold text-slate-900">Intervenants</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach($speakers as $speaker)
                <article class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    @if($speaker->photoUrl())
                        <img src="{{ $speaker->photoUrl() }}" alt="" class="h-14 w-14 shrink-0 rounded-full object-cover" loading="lazy">
                    @else
                        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-slate-100 text-lg font-bold text-slate-500" aria-hidden="true">{{ mb_strtoupper(mb_substr($speaker->name, 0, 1)) }}</span>
                    @endif
                    <div class="min-w-0">
                        <h3 class="font-semibold text-slate-900">
                            @if($speaker->website_url)<a href="{{ $speaker->website_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ $speaker->name }}</a>@else{{ $speaker->name }}@endif
                        </h3>
                        <p class="text-xs font-semibold text-slate-500">{{ $speaker->kindLabel() }} · {{ $speaker->originLabel() }}</p>
                        @if($speaker->job_title || $speaker->organization)
                            <p class="text-sm text-slate-600">{{ collect([$speaker->job_title, $speaker->organization])->filter()->implode(' · ') }}</p>
                        @endif
                        @if($speaker->bio)<p class="mt-1 text-sm text-slate-700">{{ \Illuminate\Support\Str::limit($speaker->bio, 220) }}</p>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif

@if($levels->isNotEmpty())
    <section id="sponsors" class="mt-10">
        <h2 class="font-display text-2xl font-bold text-slate-900">Sponsors et partenaires</h2>
        @foreach($levels as $level)
            <h3 class="mt-5 text-sm font-bold uppercase tracking-wide text-slate-500">{{ $level->name }}</h3>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach($level->sponsors as $sponsor)
                    @php($tag = $sponsor->website_url ? 'a' : 'div')
                    <{{ $tag }} @if($sponsor->website_url) href="{{ $sponsor->website_url }}" target="_blank" rel="noopener noreferrer sponsored" @endif class="flex min-h-16 min-w-32 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-center text-sm font-semibold text-slate-700 shadow-sm">
                        @if($sponsor->logoUrl())
                            <img src="{{ $sponsor->logoUrl() }}" alt="{{ $sponsor->name }}" class="max-h-12 max-w-40 object-contain" loading="lazy">
                        @else
                            {{ $sponsor->name }}
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        @endforeach
    </section>
@endif
