@extends('admin.layouts.app')

@section('title', 'Mandats')
@section('section', 'Mandats')

@section('content')
    <x-admin.page-header title="Mandats" subtitle="Gestion des mandats Tunivisions (un bureau par mandat), de 2016-2017 à aujourd’hui." />

    <article class="card card-flush" style="margin-bottom: 16px;">
        <div class="card-head"><h2>Ajouter un mandat</h2></div>
        <form method="post" action="{{ route('admin.mandates.store') }}" class="grid" style="gap:12px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); padding: 12px 16px 18px;">
            @csrf
            <div>
                <label for="start_year" class="text-sm">Début</label>
                <input id="start_year" type="number" name="start_year" min="2016" max="{{ now()->year + 1 }}" value="{{ old('start_year', now()->year) }}" required>
            </div>
            <div>
                <label for="end_year" class="text-sm">Fin</label>
                <input id="end_year" type="number" name="end_year" min="2017" max="{{ now()->year + 2 }}" value="{{ old('end_year', now()->year + 1) }}" required>
            </div>
            <div style="grid-column: span 2;">
                <label for="bureau_name" class="text-sm">Bureau du mandat</label>
                <input id="bureau_name" type="text" name="bureau_name" value="{{ old('bureau_name', 'Bureau Tunivisions') }}" maxlength="190" required>
            </div>
            <div style="grid-column: 1 / -1;">
                <label for="notes" class="text-sm">Notes (optionnel)</label>
                <textarea id="notes" name="notes" rows="2" maxlength="2000" placeholder="Exemple: bureau élu en AG annuelle.">{{ old('notes') }}</textarea>
            </div>
            <label style="grid-column: 1 / -1;">
                <span class="text-sm">Statut</span>
                <select name="status">
                    @foreach(\App\Models\Mandate::STATUSES as $value => $statusLabel)
                        <option value="{{ $value }}" @selected(old('status', 'upcoming') === $value)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </label>
            <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Ajouter</button>
            </div>
        </form>
    </article>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Historique des mandats</h2>
                <p class="sub">{{ $mandates->count() }} mandat(s) enregistré(s). Mandat en cours: {{ $current?->label ?? 'non défini' }}.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Mandat</th>
                        <th>Bureau</th>
                        <th>Statut</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($mandates as $mandate)
                        <tr>
                            <td>
                                <strong>{{ $mandate->label }}</strong>
                                <div class="cell-sub">{{ $mandate->start_year }} → {{ $mandate->end_year }}</div>
                            </td>
                            <td style="min-width:220px;">{{ $mandate->bureau_name }}</td>
                            <td style="min-width:200px;">
                                @php
                                    $tone = ['current' => 'success', 'upcoming' => 'info', 'closed' => 'neutral'][$mandate->status] ?? 'neutral';
                                @endphp
                                <span class="badge badge-{{ $tone }}">{{ \App\Models\Mandate::STATUSES[$mandate->status] ?? $mandate->status }}</span>
                                <form method="post" action="{{ route('admin.mandates.status', $mandate) }}" style="display:flex; gap:6px; margin-top:8px;">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" aria-label="Statut du mandat {{ $mandate->label }}">
                                        @foreach(\App\Models\Mandate::STATUSES as $value => $statusLabel)
                                            <option value="{{ $value }}" @selected($mandate->status === $value)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-secondary btn-sm">OK</button>
                                </form>
                            </td>
                            <td style="max-width:320px;">{{ $mandate->notes ?: '—' }}</td>
                            <td>
                                <div style="display:flex; justify-content:flex-end; margin-bottom:8px;">
                                    <a href="{{ route('orgchart.index', ['mandate_id' => $mandate->id]) }}" class="btn btn-secondary btn-sm">Voir l’organigramme</a>
                                </div>
                                <details>
                                    <summary class="btn btn-secondary btn-sm" style="cursor:pointer;">Modifier</summary>
                                    <form method="post" action="{{ route('admin.mandates.update', $mandate) }}" class="inline-form" style="margin-top:8px; display:grid; gap:8px; min-width: 280px;">
                                        @csrf
                                        @method('PUT')
                                        <input type="number" name="start_year" min="2016" max="{{ now()->year + 1 }}" value="{{ $mandate->start_year }}" required>
                                        <input type="number" name="end_year" min="2017" max="{{ now()->year + 2 }}" value="{{ $mandate->end_year }}" required>
                                        <input type="text" name="bureau_name" maxlength="190" value="{{ $mandate->bureau_name }}" required>
                                        <textarea name="notes" rows="2" maxlength="2000" placeholder="Notes">{{ $mandate->notes }}</textarea>
                                        <select name="status" aria-label="Statut">
                                            @foreach(\App\Models\Mandate::STATUSES as $value => $statusLabel)
                                                <option value="{{ $value }}" @selected($mandate->status === $value)>{{ $statusLabel }}</option>
                                            @endforeach
                                        </select>
                                        <div style="display:flex; gap:8px; justify-content:flex-end;">
                                            <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                        </div>
                                    </form>
                                    <div style="margin-top:8px; text-align:right;">
                                        <x-admin.delete-button :action="route('admin.mandates.destroy', $mandate)" message="Le mandat sera supprimé de l’historique." title="Supprimer ce mandat ?" />
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Aucun mandat enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
@endsection
