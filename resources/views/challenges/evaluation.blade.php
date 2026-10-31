@extends('layouts.base', ['title' => 'Évaluation — '.$challenge->title])

@php
    $pts = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',');
@endphp

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <a href="{{ route('challenges.show', $challenge) }}" class="text-sm font-semibold text-slate-500 hover:text-tlink-blue"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>{{ $challenge->title }}</a>

        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Évaluation du comité</h1>
            <p class="max-w-3xl text-sm text-slate-600">
                Thématique : <strong>{{ $challenge->theme }}</strong>. Notez chaque critère de chaque activité ; la note d’une activité est la moyenne des totaux des membres du comité.
                @if($challenge->scoringOpen())La notation est <strong class="text-emerald-700">ouverte</strong> jusqu’au {{ $challenge->results_at->format('d/m/Y H:i') }}.
                @elseif(now()->lt($challenge->ends_at))La notation ouvre à la fin du défi, le {{ $challenge->ends_at->format('d/m/Y H:i') }}.
                @else La notation est <strong>fermée</strong> : les résultats sont publiés.@endif
            </p>
        </div>

        @if(session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif

        @forelse($activities as $activity)
            @php
                $grid = $challenge->criteriaOf($activity->in_theme ? 'theme' : 'off_theme');
                $mine = $activity->scores->keyBy('criterion_id');
                $total = $totals[$activity->id] ?? null;
                $conflict = in_array($activity->id, $conflicts, true);
                $reported = $activity->report?->isValidated() === true;
                $locked = ! $challenge->scoringOpen() || $conflict || ! $reported;
            @endphp
            <section id="activite-{{ $activity->id }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-semibold text-slate-900"><a href="{{ route('activities.show', $activity) }}" class="hover:text-tlink-blue">{{ $activity->title }}</a></h2>
                        <p class="text-sm text-slate-600">{{ $activity->club->title }}@foreach($activity->organizers->where('status', 'accepted') as $organizer) · {{ $organizer->club?->title }}@endforeach — {{ $activity->typeLabel() }}, {{ $activity->starts_at->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <span class="rounded-full {{ $activity->in_theme ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700' }} px-2.5 py-1 text-xs font-semibold">{{ $activity->in_theme ? 'Thématique' : 'Hors thématique' }}</span>
                        <p class="mt-2 font-bold text-tlink-blue">{{ ($total['total'] ?? null) !== null ? $pts($total['total']).' / '.$total['max'].' pts' : 'Pas encore notée' }}</p>
                        @if($total)<p class="text-xs text-slate-500">{{ $total['evaluators'] }} évaluation(s) complète(s)</p>@endif
                    </div>
                </div>

                @if($reported)
                    @include('challenges.partials.report', ['report' => $activity->report])
                @endif

                @if($conflict)
                    <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">Conflit d’intérêt : vous appartenez à un club organisateur, vous ne pouvez pas noter cette activité.</p>
                @elseif(! $reported)
                    <p class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600"><i class="fa-solid fa-hourglass-half mr-2" aria-hidden="true"></i>
                        {{ $activity->report?->status === 'pending' ? 'Compte rendu en attente de validation du président du club.' : 'Compte rendu pas encore remis par le club.' }} L’activité sera notée une fois le compte rendu validé.</p>
                @elseif($grid->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">Aucun barème défini pour ce type d’activité.</p>
                @else
                    <form method="post" action="{{ route('challenges.score', [$challenge, $activity]) }}" class="mt-4 space-y-3">
                        @csrf
                        @foreach($grid as $criterion)
                            <div class="grid gap-2 rounded-2xl bg-slate-50 p-3 md:grid-cols-[1fr_8rem_1fr] md:items-center">
                                <div><p class="text-sm font-semibold text-slate-900">{{ $criterion->label }}</p>@if($criterion->description)<p class="text-xs text-slate-500">{{ $criterion->description }}</p>@endif</div>
                                <label class="flex items-center gap-2 text-sm"><span class="sr-only">Note pour {{ $criterion->label }}</span>
                                    <input type="number" name="points[{{ $criterion->id }}]" min="0" max="{{ $criterion->max_points }}" step="0.25" required @disabled($locked) value="{{ old('points.'.$criterion->id, $mine->has($criterion->id) ? $pts($mine[$criterion->id]->points) : '') }}" class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-right outline-none focus:border-tlink-blue"> <span class="text-slate-500">/ {{ $criterion->max_points }}</span>
                                </label>
                                <input type="text" name="comments[{{ $criterion->id }}]" maxlength="500" placeholder="Commentaire (facultatif)" aria-label="Commentaire pour {{ $criterion->label }}" @disabled($locked) value="{{ old('comments.'.$criterion->id, $mine[$criterion->id]->comment ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm outline-none focus:border-tlink-blue">
                            </div>
                        @endforeach
                        @unless($locked)<button type="submit" class="rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">Enregistrer mes notes</button>@endunless
                    </form>
                @endif
            </section>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">Aucune activité publiée à évaluer.</p>
        @endforelse
    </div>
</div>
@endsection
