@extends('admin.layouts.app')

@section('title', $event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <div class="grid-3-1">
        <article class="card card-flush" style="min-width:0">
            <div class="card-head"><h2>Vue d’ensemble</h2>@if($roleLabel)<span class="badge badge-info">{{ $roleLabel }}</span>@endif</div>
            <div class="card-body">
                <dl class="kv">
                    <div><dt>Début</dt><dd>{{ $event->starts_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Fin</dt><dd>{{ $event->ends_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Lieu</dt><dd>{{ $event->location ?: '—' }}</dd></div>
                    <div><dt>Statut</dt><dd><x-admin.badge :status="$event->status" /></dd></div>
                    <div><dt>Confirmées</dt><dd>{{ $counts['confirmed'] ?? 0 }}{{ $event->capacity ? ' / '.$event->capacity : '' }}</dd></div>
                    <div><dt>En attente</dt><dd>{{ $counts['pending'] ?? 0 }}</dd></div>
                    <div><dt>Liste d’attente</dt><dd>{{ $counts['waitlisted'] ?? 0 }}</dd></div>
                    <div><dt>Releases</dt><dd>{{ $releasesCount }}</dd></div>
                    <div><dt>Étapes du programme</dt><dd>{{ $sessionsCount }}</dd></div>
                    <div><dt>Encaissé (validé)</dt><dd>{{ number_format($paid, 2, ',', ' ') }} TND</dd></div>
                    <div><dt>Paiements à valider</dt><dd>{{ $pendingPayments }}</dd></div>
                </dl>
            </div>
        </article>

        <article class="card card-flush" style="align-self:start;min-width:0">
            <div class="card-head"><h2>Équipe OC</h2></div>
            <div class="card-body">
                @forelse($team as $member)
                    <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0">
                        <span>{{ $member->user?->name }}</span>
                        <span class="text-sm text-muted">{{ $member->job_title ?: (\App\Support\Events\EventAccess::ROLES[$member->role] ?? $member->role) }}</span>
                    </div>
                @empty
                    <p class="text-muted">Aucun membre dans l’équipe pour le moment.</p>
                @endforelse
            </div>
        </article>
    </div>
@endsection
