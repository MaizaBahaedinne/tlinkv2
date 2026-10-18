@extends('admin.layouts.app')

@section('title', 'Clubs')
@section('section', 'Clubs')

@section('content')
    <x-admin.page-header title="Clubs" subtitle="Clubs rattachés aux entités, départements et institutions.">
        <x-slot:actions>
            <a href="{{ route('admin.clubs.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouveau club</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.clubs.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un club…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach($statuses as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['status'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="entity" aria-label="Entité">
                    <option value="">Toutes les entités</option>
                    @foreach($entities as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['entity'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.clubs.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($clubs->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Rattachement</th>
                            <th class="hide-mobile">Gouvernorat</th>
                            <th>Membres</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clubs as $club)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $club->name }}</div>
                                    <div class="cell-sub">{{ $club->slug }}</div>
                                </td>
                                <td>
                                    <div>{{ $club->entity?->name ?? '—' }}</div>
                                    @if($club->department || $club->institution)
                                        <div class="cell-sub">{{ collect([$club->department?->name, $club->institution?->name])->filter()->implode(' · ') }}</div>
                                    @endif
                                </td>
                                <td class="hide-mobile">{{ $club->governorate?->name ?? '—' }}</td>
                                <td>{{ $club->memberships_count }}</td>
                                <td><x-admin.badge :status="$club->status" /></td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.clubs.edit', $club) }}" class="btn btn-secondary btn-icon btn-sm" title="Modifier" aria-label="Modifier"><x-admin.icon name="edit" :size="15" /></a>
                                    <x-admin.delete-button :action="route('admin.clubs.destroy', $club)" :message="'« '.$club->name.' » sera supprimé définitivement.'" title="Supprimer ce club ?" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $clubs->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="flag" title="Aucun club" text="Aucun club ne correspond à ces critères." />
        @endif
    </article>
@endsection
