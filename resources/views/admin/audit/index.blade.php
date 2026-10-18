@extends('admin.layouts.app')

@section('title', 'Journal d’audit')
@section('section', 'Journal d’audit')

@section('content')
    <x-admin.page-header title="Journal d’audit" subtitle="Historique complet des actions sensibles : qui a fait quoi, quand et depuis quelle adresse IP." />

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.audit.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un acteur…">
                </div>
                <select name="action" aria-label="Action">
                    <option value="">Toutes les actions</option>
                    @foreach($actions as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['action'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Du">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Au">
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($logs->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Acteur</th>
                            <th>Action</th>
                            <th class="hide-mobile">Cible</th>
                            <th class="hide-mobile">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td class="nowrap">
                                    <div class="cell-main">{{ $log->created_at?->format('d/m/Y') }}</div>
                                    <div class="cell-sub">{{ $log->created_at?->format('H:i:s') }}</div>
                                </td>
                                <td>
                                    <div class="cell-user">
                                        <x-admin.avatar :name="$log->actor?->name ?? 'Système'" :size="34" />
                                        <div>
                                            <div class="cell-main">{{ $log->actor?->name ?? 'Système' }}</div>
                                            <div class="cell-sub">{{ $log->actor?->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-main">{{ \App\Support\AuditLabels::label($log->action) }}</div>
                                    <div class="cell-sub">{{ $log->action }}</div>
                                    @if(! empty($log->metadata))
                                        <details class="text-sm" style="margin-top:4px">
                                            <summary class="text-muted" style="cursor:pointer">Détails</summary>
                                            <pre style="margin:6px 0 0;white-space:pre-wrap;font-size:12px;color:var(--muted)">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </details>
                                    @endif
                                </td>
                                <td class="hide-mobile text-sm">
                                    @if($log->target_type)
                                        {{ class_basename($log->target_type) }} <span class="text-muted">#{{ $log->target_id }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="hide-mobile text-sm text-muted nowrap">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="activity" title="Aucune entrée" text="Aucune action ne correspond à ces filtres." />
        @endif
    </article>
@endsection
