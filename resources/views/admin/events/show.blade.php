@extends('admin.layouts.app')

@section('title', $event->title)
@section('section', 'Événements')

@section('content')
    <x-admin.page-header :title="$event->title" :subtitle="$event->location ?? 'Lieu non précisé'">
        <x-slot:actions>
            <a href="{{ route('admin.events.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-primary"><x-admin.icon name="edit" :size="16" /> Modifier</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid-3-1">
        <article class="card card-flush" style="min-width:0">
            <div class="card-head">
                <div>
                    <h2>Inscriptions</h2>
                    <div class="sub">{{ $registrations->count() }} inscrit(s){{ $event->capacity ? ' sur '.$event->capacity.' places' : '' }}</div>
                </div>
            </div>

            @if($registrations->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr><th>Participant</th><th class="hide-mobile">Inscrit le</th><th>Statut</th></tr>
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
                                    <td>
                                        <form method="post" action="{{ route('admin.events.registrations.update', [$event, $registration]) }}" class="inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" aria-label="Statut de l’inscription" onchange="this.form.submit()">
                                                @foreach($registrationStatuses as $code => $label)
                                                    <option value="{{ $code }}" @selected($registration->status === $code)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <noscript><button type="submit" class="btn btn-secondary btn-sm">OK</button></noscript>
                                        </form>
                                    </td>
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
            <div class="card-head"><h2>Informations</h2><x-admin.badge :status="$event->status" /></div>
            <div class="card-body">
                <dl class="kv">
                    <div><dt>Début</dt><dd>{{ $event->starts_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Fin</dt><dd>{{ $event->ends_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Entité</dt><dd>{{ $entityName ?? 'Global' }}</dd></div>
                    <div><dt>Organisateur</dt><dd>{{ $organizerName ?? '—' }}</dd></div>
                    <div><dt>Ouverture inscr.</dt><dd>{{ $event->registration_starts_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Clôture inscr.</dt><dd>{{ $event->registration_ends_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Capacité</dt><dd>{{ $event->capacity ?? 'Illimitée' }}</dd></div>
                    <div><dt>Validation</dt><dd>{{ $event->approval_required ? 'Requise' : 'Automatique' }}</dd></div>
                </dl>
                @if($event->description)
                    <p style="margin:18px 0 0;white-space:pre-line">{{ $event->description }}</p>
                @endif
            </div>
        </article>
    </div>
@endsection
