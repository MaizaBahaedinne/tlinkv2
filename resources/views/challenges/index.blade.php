@extends('layouts.base', ['title' => 'Défis | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-6 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Défis</h1>
            <p class="max-w-2xl text-sm text-slate-600">Les bureaux des chapitres et des projets lancent des défis thématiques : les clubs invités y proposent des activités, un comité les note et les résultats sont publiés.</p>
        </div>

        @if($evaluating->isNotEmpty())
            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
                <h2 class="font-display text-lg font-semibold text-amber-900"><i class="fa-solid fa-clipboard-check mr-2" aria-hidden="true"></i>Vous êtes membre d’un comité d’évaluation</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($evaluating as $challenge)
                        <a href="{{ route('challenges.evaluation', $challenge) }}" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-amber-900 shadow-sm hover:bg-amber-100">Évaluer « {{ $challenge->title }} »</a>
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-label="Activités à venir">
            <h2 class="font-display text-lg font-semibold text-slate-900">Activités à venir</h2>
            @if(empty($upcoming))
                <p class="mt-2 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">Aucune activité publique à venir pour le moment.</p>
            @else
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    @foreach($upcoming as $entry)
                        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                            <a href="{{ $entry['url'] }}" class="font-display text-lg font-semibold text-slate-900 hover:text-tlink-blue">{{ $entry['title'] }}</a>
                            <p class="mt-1 text-sm text-slate-600"><i class="fa-regular fa-calendar mr-1 text-tlink-blue" aria-hidden="true"></i>{{ \Illuminate\Support\Carbon::parse($entry['day'])->locale('fr')->isoFormat('ddd D MMM') }} · {{ $entry['time'] }}@if($entry['location']) · {{ $entry['location'] }}@endif</p>
                            <p class="text-xs text-slate-500">{{ $entry['type'] }}@if($entry['club']) · {{ $entry['club'] }}@endif @if($entry['capacity'] !== null) · {{ $entry['registered'] }}/{{ $entry['capacity'] }} places @endif</p>
                            <div class="mt-3">
                                @if($entry['state'] === 'registered')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Inscrit</span>
                                    @if($entry['scan_url'])<a href="{{ $entry['scan_url'] }}" class="ml-2 text-xs font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> Scanner</a>@endif
                                @elseif($entry['state'] === 'waitlisted')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700"><i class="fa-regular fa-clock" aria-hidden="true"></i> Liste d’attente</span>
                                @elseif($entry['state'] === 'closed')
                                    <span class="text-xs text-slate-500">Inscriptions closes</span>
                                @elseif($entry['needs_form'])
                                    <a href="{{ $entry['url'] }}" class="inline-flex rounded-lg bg-tlink-blue px-3 py-1.5 text-xs font-semibold text-white hover:bg-tlink-blue-700">S’inscrire</a>
                                @else
                                    <form action="{{ $entry['register_url'] }}" method="post">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-tlink-blue px-3 py-1.5 text-xs font-semibold text-white hover:bg-tlink-blue-700">{{ $entry['state'] === 'full' ? 'Rejoindre la liste d’attente' : 'S’inscrire' }}</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @foreach([['En cours et à venir', $open, 'Aucun défi ouvert pour le moment.'], ['Terminés', $past, 'Aucun défi terminé.']] as [$heading, $list, $empty])
            <section>
                <h2 class="font-display text-lg font-semibold text-slate-900">{{ $heading }}</h2>
                @if($list->isEmpty())
                    <p class="mt-2 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">{{ $empty }}</p>
                @else
                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        @foreach($list as $challenge)
                            <a href="{{ route('challenges.show', $challenge) }}" class="block rounded-3xl border border-slate-200 bg-white p-5 shadow-soft transition hover:border-tlink-blue">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-display text-lg font-semibold text-slate-900">{{ $challenge->title }}</h3>
                                    <span class="shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-tlink-blue">{{ \App\Models\Challenge::PHASES[$challenge->phase()] }}</span>
                                </div>
                                <p class="mt-1 text-sm font-medium text-slate-700"><i class="fa-solid fa-bullseye mr-1 text-tlink-blue" aria-hidden="true"></i>{{ $challenge->theme }}</p>
                                <p class="mt-3 text-xs text-slate-500">Du {{ $challenge->starts_at->format('d/m/Y') }} au {{ $challenge->ends_at->format('d/m/Y') }} · {{ $challenge->activities_count }} activité(s) · {{ \App\Support\OrgScopes::label($challenge->scope) }}</p>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>
@endsection
