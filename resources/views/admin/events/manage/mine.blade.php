@extends('admin.layouts.app')

@section('title', 'Mes événements (OC)')
@section('section', 'Événements')

@section('content')
    <x-admin.page-header title="Mes événements (équipe OC)" subtitle="Les événements dont vous faites partie de l’équipe d’organisation." />

    <article class="card card-flush">
        @if($events->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Événement</th><th class="hide-mobile">Date</th><th>Mon rôle</th><th></th></tr></thead>
                    <tbody>
                        @foreach($events as $event)
                            <tr>
                                <td class="cell-main">{{ $event->title }}</td>
                                <td class="hide-mobile text-sm text-muted nowrap">{{ $event->starts_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ \App\Support\Events\EventAccess::ROLES[$event->team->first()?->role] ?? 'Responsable de bus' }}</td>
                                <td class="text-right"><a href="{{ \App\Support\Events\EventAccess::canView(auth()->user(), $event) ? route('admin.events.overview', $event) : route('admin.events.transport.index', $event) }}" class="btn btn-secondary btn-sm">Gérer</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty icon="calendar" title="Aucun événement" text="Vous ne faites partie d’aucune équipe d’organisation." />
        @endif
    </article>
@endsection
