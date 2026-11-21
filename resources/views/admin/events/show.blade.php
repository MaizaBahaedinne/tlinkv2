@extends('admin.layouts.app')

@section('title', $event->title)
@section('section', 'Événements')

@section('content')
    @php
        $coverUrl = $event->coverUrl();
        $place = collect([$event->location, $event->governorate?->name])->filter()->implode(' · ');
        $confirmed = ($registrationCounts['confirmed'] ?? 0) + ($registrationCounts['attended'] ?? 0);
        $pending = $registrationCounts['pending'] ?? 0;
        $waitlisted = $registrationCounts['waitlisted'] ?? 0;
        $fill = $event->capacity ? min(100, (int) round($confirmed / max(1, $event->capacity) * 100)) : null;
    @endphp

    <section class="ev-hero" @if($coverUrl) style="--ev-cover:url('{{ $coverUrl }}')" @endif>
        <div class="ev-hero-body">
            <div class="ev-hero-top">
                <x-admin.badge :status="$event->status" :label="$statuses[$event->status] ?? null" />
                <span class="ev-chip">{{ $scopeName ?? 'Global' }}</span>
                @if($event->approval_required)<span class="ev-chip">Validation requise</span>@endif
            </div>
            <h1 class="ev-hero-title">{{ $event->title }}</h1>
            <div class="ev-hero-meta">
                <span><x-admin.icon name="calendar" :size="15" /> {{ $event->starts_at?->format('d/m/Y H:i') ?? 'Date à définir' }}{{ $event->ends_at ? ' → '.$event->ends_at->format('d/m/Y H:i') : '' }}</span>
                <span><x-admin.icon name="users" :size="15" /> {{ $place ?: 'Lieu non précisé' }}</span>
            </div>
            <div class="ev-hero-actions">
                <a href="{{ route('admin.events.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
                <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-secondary"><x-admin.icon name="layers" :size="16" /> Gérer (billetterie, programme, OC…)</a>
                <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-primary"><x-admin.icon name="edit" :size="16" /> Modifier</a>
            </div>
        </div>
    </section>

    <section class="stats" style="margin-bottom:22px">
        <div class="stat">
            <span class="stat-icon tone-blue"><x-admin.icon name="users" :size="22" /></span>
            <div>
                <div class="stat-label">Inscrits</div>
                <div class="stat-value">{{ $registrations->count() }}</div>
                <div class="stat-hint">{{ $event->capacity ? 'sur '.$event->capacity.' places' : 'places illimitées' }}</div>
            </div>
        </div>
        <div class="stat">
            <span class="stat-icon tone-green"><x-admin.icon name="check" :size="22" /></span>
            <div>
                <div class="stat-label">Confirmés</div>
                <div class="stat-value">{{ $confirmed }}</div>
                @if($fill !== null)<div class="stat-hint">{{ $fill }} % de remplissage</div>@endif
            </div>
        </div>
        <div class="stat">
            <span class="stat-icon tone-amber"><x-admin.icon name="clock" :size="22" /></span>
            <div>
                <div class="stat-label">En attente</div>
                <div class="stat-value">{{ $pending }}</div>
                <div class="stat-hint">{{ $waitlisted }} en liste d’attente</div>
            </div>
        </div>
    </section>

    @include('admin.events.partials.imports')

    <div class="grid-3-1">
        <article class="card card-flush" style="min-width:0">
            <div class="card-head">
                <div>
                    <h2>Inscriptions</h2>
                    <div class="sub">Le statut suit le parcours du participant (validation, paiement, présence) : il n’est pas modifiable ici.</div>
                </div>
            </div>

            @if($registrations->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr><th>Participant</th><th class="hide-mobile">Inscrit le</th><th class="hide-mobile">Release</th><th>Paiement</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            @foreach($registrations as $registration)
                                <tr>
                                    <td>
                                        <div class="cell-user">
                                            <x-admin.avatar :name="$registration->user?->name" :size="34" />
                                            <div>
                                                <div class="cell-main">{{ $registration->user?->name ?? 'Utilisateur supprimé' }}</div>
                                                <div class="cell-sub">{{ $registration->user?->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="hide-mobile text-sm text-muted nowrap">{{ ($registration->registered_at ?? $registration->created_at)?->format('d/m/Y H:i') }}</td>
                                    <td class="hide-mobile text-sm">{{ $registration->release?->name ?? '—' }}</td>
                                    <td>@include('admin.events.manage._payment_badge', ['status' => $registration->payment_status])</td>
                                    <td><x-admin.badge :status="$registration->status" :label="$registrationStatuses[$registration->status] ?? null" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-admin.empty icon="users" title="Aucune inscription" text="Personne ne s’est encore inscrit à cet événement." />
            @endif
        </article>

        <article class="card card-flush" style="align-self:start;min-width:0">
            <div class="card-head"><h2>Informations</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <div><dt>Périmètre</dt><dd>{{ $scopeName ?? 'Global' }}</dd></div>
                    <div><dt>Organisateur</dt><dd>{{ $organizerName ?? '—' }}</dd></div>
                    <div><dt>Ouverture inscr.</dt><dd>{{ $event->registration_starts_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Clôture inscr.</dt><dd>{{ $event->registration_ends_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Capacité</dt><dd>{{ $event->capacity ?? 'Illimitée' }}</dd></div>
                    <div><dt>Gouvernorat</dt><dd>{{ $event->governorate?->name ?? '—' }}</dd></div>
                    <div><dt>Prix</dt><dd>{{ $event->priceLabel() ?? '—' }}</dd></div>
                    <div><dt>Validation</dt><dd>{{ $event->approval_required ? 'Requise' : 'Automatique' }}</dd></div>
                </dl>
                @if($event->description)
                    <p style="margin:18px 0 0;white-space:pre-line">{{ $event->description }}</p>
                @endif
            </div>
        </article>
    </div>
@endsection
