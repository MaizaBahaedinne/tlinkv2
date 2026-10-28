@extends('layouts.base', ['title' => 'Mes badges | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl font-semibold text-slate-900">Mes badges</h1>
                    <p class="mt-1 text-sm text-slate-600">Relevez les missions pour obtenir des badges : plus la mission est complexe, plus la catégorie est élevée.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center">
                    <p class="text-2xl font-semibold text-slate-900">{{ $earnedCount }} / {{ $badges->count() }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Badges obtenus</p>
                </div>
            </div>
        </section>

        @foreach($tiers as $tierKey => $tier)
            @php $tierBadges = $badges->filter(fn (array $row): bool => $row['badge']->tier === $tierKey); @endphp
            @continue($tierBadges->isEmpty())

            <section class="space-y-3">
                <h2 class="flex items-center gap-2 font-display text-lg font-semibold text-slate-900">
                    <span class="rounded-full border px-3 py-0.5 text-xs font-bold uppercase tracking-wide {{ $tier['chip'] }}">{{ $tier['label'] }}</span>
                    <span class="text-sm font-normal text-slate-500">{{ $tierBadges->where('earned', true)->count() }} / {{ $tierBadges->count() }}</span>
                </h2>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($tierBadges as $row)
                        @php $badge = $row['badge']; @endphp
                        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft" data-badge="{{ $badge->slug }}" data-earned="{{ $row['earned'] ? '1' : '0' }}">
                            <div class="flex items-start gap-4">
                                <x-badge-medal :badge="$badge" size="lg" :locked="! $row['earned']" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-display text-base font-semibold text-slate-900">{{ $badge->name }}</h3>
                                        @if($row['earned'])
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-emerald-700">Obtenu</span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-slate-600">En cours</span>
                                        @endif
                                    </div>
                                    @if($badge->description)
                                        <p class="mt-1 text-sm text-slate-600">{{ $badge->description }}</p>
                                    @endif
                                    @if($row['earned'] && $row['awarded_at'])
                                        <p class="mt-1 text-xs text-slate-500">Obtenu le {{ $row['awarded_at']->locale('fr')->isoFormat('D MMMM YYYY') }}</p>
                                    @endif
                                    <div class="mt-3 flex items-center gap-3">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $row['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="h-full rounded-full bg-gradient-to-r {{ $badge->gradient() }}" style="width: {{ $row['percent'] }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-slate-600">{{ $row['percent'] }}%</span>
                                    </div>
                                </div>
                            </div>

                            <details class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Voir les missions ({{ count($row['missions']) }})</summary>
                                <ol class="mt-3 space-y-3">
                                    @foreach($row['missions'] as $missionRow)
                                        <li>
                                            <p class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                                <i class="fa-solid {{ $missionRow['done'] ? 'fa-circle-check text-emerald-600' : 'fa-circle text-slate-300' }}"></i>
                                                {{ $missionRow['mission']->title }}
                                            </p>
                                            @if($missionRow['mission']->description)
                                                <p class="ml-6 text-xs text-slate-500">{{ $missionRow['mission']->description }}</p>
                                            @endif
                                            <ul class="ml-6 mt-2 space-y-2">
                                                @foreach($missionRow['checkpoints'] as $point)
                                                    @php $ratio = (int) floor($point['current'] / $point['target'] * 100); @endphp
                                                    <li>
                                                        <div class="flex items-center justify-between gap-3 text-xs">
                                                            <span class="{{ $point['done'] ? 'text-slate-500 line-through' : 'text-slate-700' }}">{{ $point['checkpoint']->title }}</span>
                                                            <span class="shrink-0 font-semibold text-slate-600">{{ $point['current'] }} / {{ $point['target'] }}</span>
                                                        </div>
                                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                                            <div class="h-full rounded-full {{ $point['done'] ? 'bg-emerald-500' : 'bg-tlink-blue' }}" style="width: {{ $ratio }}%"></div>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @endforeach
                                </ol>
                            </details>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if($badges->isEmpty())
            <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600 shadow-soft">
                <i class="fa-solid fa-award text-3xl text-slate-300"></i>
                <p class="mt-3">Les premières missions arrivent bientôt. Revenez vite !</p>
            </section>
        @endif
    </main>
</div>
@endsection
