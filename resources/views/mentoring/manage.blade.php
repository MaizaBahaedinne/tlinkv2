@extends('layouts.base', ['title' => 'Gestion des tickets | Mentorat | T.Link'])

@section('content')
@php
    $input = 'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue';
    $openCount = collect(\App\Models\MentoringTicket::OPEN)->sum(fn ($key) => $counts[$key] ?? 0);
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Gestion des tickets</h1>
            <p class="text-sm text-slate-600">Recevez les tickets, qualifiez-les puis affectez-les à un mentor.</p>
        </div>

        @include('mentoring.partials.tabs')

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
            <a href="{{ route('mentoring.manage') }}" class="rounded-2xl border bg-white px-3 py-3 text-center shadow-soft {{ $status === 'open' ? 'border-tlink-blue' : 'border-slate-200' }}">
                <p class="text-2xl font-semibold text-slate-900">{{ $openCount }}</p>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">À traiter</p>
            </a>
            @foreach(\App\Models\MentoringTicket::STATUSES as $key => [$label])
                <a href="{{ route('mentoring.manage', ['statut' => $key]) }}" class="rounded-2xl border bg-white px-3 py-3 text-center shadow-soft {{ $status === $key ? 'border-tlink-blue' : 'border-slate-200' }}">
                    <p class="text-2xl font-semibold text-slate-900">{{ $counts[$key] ?? 0 }}</p>
                    <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                </a>
            @endforeach
        </div>

        <form method="get" action="{{ route('mentoring.manage') }}" class="flex flex-wrap items-end gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-soft">
            <div>
                <label for="f-statut" class="text-xs font-semibold text-slate-600">État</label>
                <select id="f-statut" name="statut" class="mt-1 block {{ $input }}">
                    <option value="open" @selected($status === 'open')>À traiter</option>
                    <option value="all" @selected($status === 'all')>Tous</option>
                    @foreach(\App\Models\MentoringTicket::STATUSES as $key => [$label])
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-priorite" class="text-xs font-semibold text-slate-600">Priorité</label>
                <select id="f-priorite" name="priorite" class="mt-1 block {{ $input }}">
                    <option value="">Toutes</option>
                    @foreach(\App\Models\MentoringTicket::PRIORITIES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['priorite'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-public" class="text-xs font-semibold text-slate-600">Concerne</label>
                <select id="f-public" name="public" class="mt-1 block {{ $input }}">
                    <option value="">Tous</option>
                    @foreach(\App\Models\MentoringTicket::AUDIENCES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['public'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[12rem] flex-1">
                <label for="f-q" class="text-xs font-semibold text-slate-600">Recherche</label>
                <input id="f-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="80" placeholder="Référence, titre ou demandeur" class="mt-1 block w-full {{ $input }}">
            </div>
            <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Filtrer</button>
        </form>

        @include('mentoring.partials.ticket-list', ['tickets' => $tickets, 'showRequester' => true, 'showMentor' => true, 'showPriority' => true, 'empty' => 'Aucun ticket ne correspond à ces critères.'])
    </div>
</div>
@endsection
