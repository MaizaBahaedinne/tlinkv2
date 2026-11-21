@extends('admin.layouts.app')

@section('title', 'Inscriptions — '.$event->title)
@section('section', 'Événements')

@php
    $counts = $registrations->countBy('status');
    $active = ($counts['confirmed'] ?? 0) + ($counts['attended'] ?? 0);
@endphp

@section('content')
    @include('admin.events.manage._header')

    <div class="stats">
        <x-admin.stat label="Inscrits" :value="$registrations->count().($event->capacity ? ' / '.$event->capacity : '')" icon="users" tone="blue" />
        <x-admin.stat label="Confirmées" :value="$active" icon="check" tone="green" />
        <x-admin.stat label="En attente" :value="($counts['pending'] ?? 0) + ($counts['waitlisted'] ?? 0)" icon="clock" tone="amber" />
        <x-admin.stat label="Annulées / refusées" :value="($counts['cancelled'] ?? 0) + ($counts['rejected'] ?? 0)" icon="inbox" tone="red" />
    </div>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Inscriptions</h2>
                <div class="sub">Affichage seul : le statut d’une inscription se gère depuis la fiche de l’événement.</div>
            </div>
            @if($registrations->count() > 8)
                <input type="search" placeholder="Rechercher un participant…" aria-label="Rechercher" data-table-filter="#registrations-table" style="max-width:260px">
            @endif
        </div>

        @if($registrations->count())
            <div class="table-wrap">
                <table class="tbl" id="registrations-table">
                    <thead><tr><th>Participant</th><th class="hide-mobile">Release</th><th>Montant dû</th><th>Paiement</th><th>Statut</th></tr></thead>
                    <tbody>
                        @foreach($registrations as $registration)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $registration->user?->name ?? 'Utilisateur supprimé' }}</div>
                                    <div class="cell-sub">{{ $registration->user?->email }}</div>
                                </td>
                                <td class="hide-mobile">{{ $registration->release?->name ?? '—' }}</td>
                                <td class="nowrap">{{ (float) $registration->amount_due > 0 ? number_format((float) $registration->amount_due, 2, ',', ' ').' TND' : 'Gratuit' }}</td>
                                <td>@include('admin.events.manage._payment_badge', ['status' => $registration->payment_status])</td>
                                <td><x-admin.badge :status="$registration->status" :label="$statuses[$registration->status] ?? $registration->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty icon="users" title="Aucune inscription" text="Personne ne s’est encore inscrit à cet événement." />
        @endif
    </article>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-table-filter]').forEach(function (input) {
        var rows = document.querySelectorAll(input.getAttribute('data-table-filter') + ' tbody tr');
        input.addEventListener('input', function () {
            var needle = input.value.trim().toLowerCase();
            rows.forEach(function (row) { row.hidden = needle !== '' && row.textContent.toLowerCase().indexOf(needle) === -1; });
        });
    });
</script>
@endpush
