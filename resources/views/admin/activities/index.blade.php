@extends('admin.layouts.app')

@section('title', 'Activités des clubs')
@section('section', 'Défis')

@section('content')
    <x-admin.page-header title="Activités des clubs" subtitle="Proposez des activités dans un défi, suivez les inscriptions et validez la présence par QR code.">
        <x-slot:actions>
            <a href="{{ route('admin.activities.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvelle activité</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($invitations->count())
        <article class="card card-flush">
            <div class="card-head"><div><h2>Invitations à co-organiser</h2><div class="sub">Un autre club vous propose de co-organiser une activité : la note du comité compte alors aussi pour votre club.</div></div></div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Activité</th><th>Club porteur</th><th>Votre club</th><th></th></tr></thead>
                    <tbody>
                        @foreach($invitations as $invitation)
                            <tr>
                                <td><a href="{{ route('activities.show', $invitation->activity) }}" class="cell-main">{{ $invitation->activity->title }}</a><div class="cell-sub">{{ $invitation->activity->challenge?->title }} · {{ $invitation->activity->starts_at->format('d/m/Y H:i') }}</div></td>
                                <td>{{ $invitation->activity->club?->title }}</td>
                                <td>{{ $invitation->club?->title }}</td>
                                <td class="cell-actions">
                                    <form method="post" action="{{ route('admin.activities.organizers.respond', $invitation) }}" class="inline-form">@csrf @method('PATCH')<input type="hidden" name="status" value="accepted"><button class="btn btn-success btn-sm" type="submit">Accepter</button></form>
                                    <form method="post" action="{{ route('admin.activities.organizers.respond', $invitation) }}" class="inline-form">@csrf @method('PATCH')<input type="hidden" name="status" value="declined"><button class="btn btn-secondary btn-sm" type="submit">Refuser</button></form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>
    @endif

    <article class="card card-flush">
        @if($activities->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Activité</th><th class="hide-mobile">Défi</th><th>Club</th><th>Public</th><th>Inscrits</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach($activities as $activity)
                            <tr>
                                <td><a href="{{ route('admin.activities.show', $activity) }}" class="cell-main">{{ $activity->title }}</a><div class="cell-sub">{{ $activity->typeLabel() }} · {{ $activity->starts_at->format('d/m/Y H:i') }} · {{ $activity->in_theme ? 'Thématique' : 'Hors thématique' }}</div></td>
                                <td class="hide-mobile">{{ $activity->challenge?->title ?? 'Activité libre' }}</td>
                                <td>{{ $activity->club?->title }}</td>
                                <td class="text-sm">{{ $activity->audienceLabel() }}</td>
                                <td>{{ $activity->registrations_count }}@if($activity->capacity) <span class="text-muted">/ {{ $activity->capacity }}</span>@endif</td>
                                <td><x-admin.badge :status="$activity->status" /></td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.activities.show', $activity) }}" class="btn btn-secondary btn-icon btn-sm" title="Détails" aria-label="Détails"><x-admin.icon name="eye" :size="15" /></a>
                                    @if($manageable === null || in_array($activity->club_id, $manageable, true))
                                        <a href="{{ route('admin.activities.edit', $activity) }}" class="btn btn-secondary btn-icon btn-sm" title="Modifier" aria-label="Modifier"><x-admin.icon name="edit" :size="15" /></a>
                                        <x-admin.delete-button :action="route('admin.activities.destroy', $activity)" :message="'« '.$activity->title.' » sera supprimée définitivement ; les éventuels inscrits seront prévenus.'" title="Supprimer cette activité ?" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $activities->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="calendar" title="Aucune activité" text="Aucune activité n’a encore été proposée par vos clubs." />
        @endif
    </article>
@endsection
