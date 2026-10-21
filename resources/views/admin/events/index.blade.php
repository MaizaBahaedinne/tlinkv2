@extends('admin.layouts.app')

@section('title', 'Événements')
@section('section', 'Événements')

@section('content')
    <x-admin.page-header title="Événements" subtitle="Planifiez les événements et suivez les inscriptions.">
        <x-slot:actions>
            <a href="{{ route('admin.events.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvel événement</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.events.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un titre ou un lieu…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach($statuses as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="scope" aria-label="Périmètre">
                    <option value="">Toutes les entités</option>
                    @foreach($scopes as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['scope'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.events.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($events->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th>Date</th>
                            <th class="hide-mobile">Entité</th>
                            <th>Inscrits</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($events as $event)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.events.show', $event) }}" class="cell-main">{{ $event->title }}</a>
                                    <div class="cell-sub">{{ $event->location ?? 'Lieu non précisé' }}</div>
                                </td>
                                <td class="nowrap text-sm">{{ $event->starts_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="hide-mobile">{{ \App\Support\OrgScopes::label($event->scope) ?? 'Global' }}</td>
                                <td>{{ $event->registrations_count }}@if($event->capacity) <span class="text-muted">/ {{ $event->capacity }}</span>@endif</td>
                                <td><x-admin.badge :status="$event->status" /></td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.events.show', $event) }}" class="btn btn-secondary btn-icon btn-sm" title="Détails" aria-label="Détails"><x-admin.icon name="eye" :size="15" /></a>
                                    <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-secondary btn-icon btn-sm" title="Modifier" aria-label="Modifier"><x-admin.icon name="edit" :size="15" /></a>
                                    <x-admin.delete-button :action="route('admin.events.destroy', $event)" :message="'« '.$event->title.' » sera supprimé définitivement.'" title="Supprimer cet événement ?" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $events->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="calendar" title="Aucun événement" text="Aucun événement ne correspond à ces critères." />
        @endif
    </article>
@endsection
