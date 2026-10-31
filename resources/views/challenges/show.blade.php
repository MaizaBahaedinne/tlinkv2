@extends('layouts.base', ['title' => $challenge->title.' | Défis'])

@php
    $pts = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',');
@endphp

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <a href="{{ route('challenges.index') }}" class="text-sm font-semibold text-slate-500 hover:text-tlink-blue"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Tous les défis</a>

        <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ \App\Support\OrgScopes::label($challenge->scope) }}</p>
                    <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $challenge->title }}</h1>
                    <p class="mt-1 text-base font-medium text-tlink-blue"><i class="fa-solid fa-bullseye mr-1" aria-hidden="true"></i>Thématique : {{ $challenge->theme }}</p>
                </div>
                <span class="rounded-full bg-rose-50 px-3 py-1 text-sm font-semibold text-tlink-blue">{{ $challenge->phaseLabel() }}</span>
            </div>
            @if($challenge->description)<p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $challenge->description }}</p>@endif

            <ol class="mt-5 grid gap-3 sm:grid-cols-4">
                @foreach([['Début', $challenge->starts_at, 'fa-flag'], ['Fin des activités', $challenge->ends_at, 'fa-hourglass-end'], ['Évaluation', $challenge->evaluation_at, 'fa-clipboard-check'], ['Résultats', $challenge->results_at, 'fa-trophy']] as [$label, $date, $icon])
                    <li class="rounded-2xl bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><i class="fa-solid {{ $icon }} mr-1" aria-hidden="true"></i>{{ $label }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $date->format('d/m/Y') }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-5 flex flex-wrap gap-2">
                @if($canPropose)<a href="{{ route('admin.activities.create', ['defi' => $challenge->id]) }}" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-plus" aria-hidden="true"></i> Proposer une activité pour mon club</a>@endif
                @if($isCommittee)<a href="{{ route('challenges.evaluation', $challenge) }}" class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Noter les activités</a>@endif
            </div>
        </header>

        @if($results)
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-xl font-semibold text-slate-900"><i class="fa-solid fa-trophy mr-2 text-amber-500" aria-hidden="true"></i>Résultats</h2>
                @if($ranking->isEmpty())
                    <p class="mt-2 text-sm text-slate-500">Aucune activité n’a été notée.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-slate-500"><tr><th class="py-2 pr-3">Rang</th><th class="py-2 pr-3">Club</th><th class="py-2 pr-3">Activités</th><th class="py-2 pr-3">Thématique</th><th class="py-2 pr-3">Hors thématique</th><th class="py-2">Total</th></tr></thead>
                            <tbody>
                                @foreach($ranking as $row)
                                    <tr class="border-t border-slate-100">
                                        <td class="py-2 pr-3 font-bold {{ $row['rank'] === 1 ? 'text-amber-500' : 'text-slate-700' }}">{{ $row['rank'] }}</td>
                                        <td class="py-2 pr-3 font-semibold text-slate-900">{{ $row['club']->title }}</td>
                                        <td class="py-2 pr-3">{{ $row['activities'] }}</td>
                                        <td class="py-2 pr-3">{{ $pts($row['theme_points']) }}</td>
                                        <td class="py-2 pr-3">{{ $pts($row['off_theme_points']) }}</td>
                                        <td class="py-2 font-bold text-tlink-blue">{{ $pts($row['points']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-xl font-semibold text-slate-900">Barèmes de notation</h2>
            <div class="mt-4 grid gap-5 md:grid-cols-2">
                @foreach(\App\Models\Challenge::KINDS as $kind => $label)
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</h3>
                        @forelse($challenge->criteriaOf($kind) as $criterion)
                            <div class="mt-2 flex items-start justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                <div><p class="font-semibold text-slate-900">{{ $criterion->label }}</p>@if($criterion->description)<p class="text-xs text-slate-500">{{ $criterion->description }}</p>@endif</div>
                                <span class="shrink-0 font-bold text-tlink-blue">{{ $criterion->max_points }} pts</span>
                            </div>
                        @empty
                            <p class="mt-2 text-sm text-slate-500">{{ $kind === 'off_theme' ? 'Les activités hors thématique ne sont pas acceptées.' : 'Aucun critère.' }}</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-slate-500">Comité d’évaluation : {{ $challenge->committee->pluck('name')->implode(', ') ?: '—' }}.</p>
        </section>

        <section>
            <h2 class="font-display text-xl font-semibold text-slate-900">Activités des clubs</h2>
            @if($activities->isEmpty())
                <p class="mt-2 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">Aucune activité publiée pour le moment.</p>
            @else
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    @foreach($activities as $activity)
                        <a href="{{ route('activities.show', $activity) }}" class="block rounded-3xl border border-slate-200 bg-white p-5 shadow-soft transition hover:border-tlink-blue">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-display text-base font-semibold text-slate-900">{{ $activity->title }}</h3>
                                <span class="shrink-0 rounded-full {{ $activity->in_theme ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700' }} px-2.5 py-1 text-xs font-semibold">{{ $activity->in_theme ? 'Thématique' : 'Hors thématique' }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $activity->club?->title }}@foreach($activity->organizers->where('status', 'accepted') as $organizer) · {{ $organizer->club?->title }}@endforeach</p>
                            <p class="mt-2 text-xs text-slate-500">{{ $activity->typeLabel() }} · {{ $activity->starts_at->format('d/m/Y H:i') }} · {{ $activity->audienceLabel() }}</p>
                            @if($results && ($totals[$activity->id]['total'] ?? null) !== null)<p class="mt-2 text-sm font-bold text-tlink-blue">{{ $pts($totals[$activity->id]['total']) }} / {{ $totals[$activity->id]['max'] }} pts</p>@endif
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
