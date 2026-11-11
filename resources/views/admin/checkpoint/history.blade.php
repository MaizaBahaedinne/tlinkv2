@extends('admin.layouts.app')

@section('title', 'Historique des pointages')
@section('section', 'Cartes et pointage')

@section('content')
    <x-admin.page-header title="Historique des pointages" subtitle="Entrées et sorties enregistrées dans votre périmètre.">
        <x-slot:actions>
            <a href="{{ route('admin.checkpoint.index') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Pointer</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.checkpoint-history.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Membre, numéro de carte ou cible…">
                </div>
                <select name="type" aria-label="Type">
                    <option value="">Tous les types</option>
                    @foreach($types as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['type'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="direction" aria-label="Sens">
                    <option value="">Entrées et sorties</option>
                    @foreach($directions as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['direction'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" aria-label="Date">
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.checkpoint-history.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($scans->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Date</th><th>Membre</th><th>Cible</th><th>Sens</th><th class="hide-mobile">Mode</th><th class="hide-mobile">Pointé par</th></tr>
                    </thead>
                    <tbody>
                        @foreach($scans as $scan)
                            <tr>
                                <td class="nowrap text-sm">{{ $scan->scanned_at->format('d/m/Y H:i:s') }}</td>
                                <td><span class="cell-main">{{ $scan->user?->name }}</span><div class="cell-sub">{{ $scan->card?->number }}</div></td>
                                <td><span class="cell-main">{{ $scan->subject_label }}</span><div class="cell-sub">{{ $types[$scan->subject_type] ?? $scan->subject_type }}</div></td>
                                <td><span class="badge badge-{{ $scan->direction === 'in' ? 'success' : 'neutral' }}">{{ $directions[$scan->direction] ?? $scan->direction }}</span></td>
                                <td class="hide-mobile">{{ $methods[$scan->method] ?? $scan->method }}</td>
                                <td class="hide-mobile">{{ $scan->scanner?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $scans->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="clock" title="Aucun pointage" text="Aucun pointage ne correspond à ces critères." />
        @endif
    </article>
@endsection
