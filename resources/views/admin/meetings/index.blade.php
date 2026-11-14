@extends('admin.layouts.app')

@section('title', 'Réunions')
@section('section', 'Gouvernance')

@section('content')
    <x-admin.page-header title="Réunions" subtitle="Planifiez les réunions de votre club : date, lieu, ordre du jour, invitations, présences et décisions point par point.">
        <x-slot:actions>
            @if($canCreate)
                <a href="{{ route('admin.meetings.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvelle réunion</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <form method="get" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div class="field">
                <label for="when">Période</label>
                <select id="when" name="when">
                    <option value="upcoming" @selected($filters['when'] === 'upcoming')>À venir</option>
                    <option value="past" @selected($filters['when'] === 'past')>Passées</option>
                    <option value="all" @selected($filters['when'] === 'all')>Toutes</option>
                </select>
            </div>
            <div class="field">
                <label for="club">Club</label>
                <select id="club" name="club">
                    <option value="">Tous</option>
                    @foreach($clubOptions as $id => $title)<option value="{{ $id }}" @selected($filters['club'] === $id)>{{ $title }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-secondary" type="submit"><x-admin.icon name="search" :size="16" /> Filtrer</button>
        </form>

        @if($meetings->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Réunion</th><th>Date</th><th class="hide-mobile">Lieu</th><th class="hide-mobile">Points</th><th class="hide-mobile">Invités</th><th>Statut</th></tr></thead>
                    <tbody>
                        @foreach($meetings as $meeting)
                            <tr>
                                <td><a class="cell-main" href="{{ route('admin.meetings.show', $meeting) }}">{{ $meeting->title }}</a><div class="cell-sub">{{ $meeting->club?->title ?? 'Fondation' }}</div></td>
                                <td>{{ $meeting->starts_at->format('d/m/Y H:i') }}<div class="cell-sub">{{ $meeting->duration_minutes }} min</div></td>
                                <td class="hide-mobile">{{ $meeting->location ?: ($meeting->online_url ? 'En ligne' : '—') }}</td>
                                <td class="hide-mobile">{{ $meeting->items_count }}</td>
                                <td class="hide-mobile">{{ $meeting->attendees_count }}</td>
                                <td><span class="badge badge-{{ $meeting->status === 'held' ? 'success' : ($meeting->status === 'cancelled' ? 'neutral' : 'info') }}">{{ \App\Models\Meeting::STATUSES[$meeting->status] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $meetings->links() }}</div>
        @else
            <x-admin.empty icon="calendar" title="Aucune réunion" text="Planifiez votre première réunion." />
        @endif
    </article>
@endsection
