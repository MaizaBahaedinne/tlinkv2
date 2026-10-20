@extends('admin.layouts.app')

@section('title', 'Entités')
@section('section', 'Entités')

@section('content')
    <x-admin.page-header title="Entités" subtitle="Fondation, branches et structures de premier niveau auxquelles se rattachent départements, institutions et clubs.">
        @if($canCreate)
            <x-slot:actions>
                <a href="{{ route('admin.entities.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvelle entité</a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.entities.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher par nom ou catégorie…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actives</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactives</option>
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.entities.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($entities->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Entité</th>
                            <th class="hide-mobile">Catégorie</th>
                            <th class="hide-mobile">Départements</th>
                            <th class="hide-mobile">Institutions</th>
                            <th>Clubs</th>
                            <th class="hide-mobile">Adhérents</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entities as $entity)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $entity->name }}</div>
                                    <div class="cell-sub">{{ $entity->slug }}</div>
                                </td>
                                <td class="hide-mobile">{{ $entity->category }}</td>
                                <td class="hide-mobile"><a href="{{ route('admin.departments.index', ['entity' => $entity->id]) }}">{{ $entity->departments_count }}</a></td>
                                <td class="hide-mobile"><a href="{{ route('admin.institutions.index', ['entity' => $entity->id]) }}">{{ $entity->institutions_count }}</a></td>
                                <td><a href="{{ route('admin.clubs.index', ['entity' => $entity->id]) }}">{{ $entity->clubs_count }}</a></td>
                                <td class="hide-mobile">{{ $entity->memberships_count }}</td>
                                <td><x-admin.badge :status="$entity->is_active ? 'active' : 'inactive'" /></td>
                                <td class="cell-actions">
                                    <a href="{{ route('orgchart.locate', ['entity', $entity->id]) }}" class="btn btn-secondary btn-icon btn-sm" title="Voir dans l'organigramme" aria-label="Voir dans l'organigramme"><x-admin.icon name="network" :size="15" /></a>
                                    <a href="{{ route('admin.entities.edit', $entity) }}" class="btn btn-secondary btn-icon btn-sm" title="Modifier" aria-label="Modifier"><x-admin.icon name="edit" :size="15" /></a>
                                    @if($canCreate)
                                        <x-admin.delete-button :action="route('admin.entities.destroy', $entity)" :message="'« '.$entity->name.' » sera supprimée définitivement.'" title="Supprimer cette entité ?" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $entities->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="building" title="Aucune entité" text="Aucune entité ne correspond à ces critères." />
        @endif
    </article>
@endsection
