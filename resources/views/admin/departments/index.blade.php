@extends('admin.layouts.app')

@section('title', 'Départements')
@section('section', 'Départements')

@section('content')
    <x-admin.page-header title="Départements" subtitle="Subdivisions internes de chaque entité.">
        <x-slot:actions>
            <a href="{{ route('admin.departments.create', array_filter(['entity' => $filters['entity'] ?? null])) }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouveau département</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.departments.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un département…">
                </div>
                <select name="entity" aria-label="Entité">
                    <option value="">Toutes les entités</option>
                    @foreach($entities as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['entity'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.departments.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($departments->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Département</th>
                            <th>Entité</th>
                            <th>Clubs</th>
                            <th class="hide-mobile">Adhérents</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($departments as $department)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $department->name }}</div>
                                    @if($department->description)
                                        <div class="cell-sub">{{ \Illuminate\Support\Str::limit($department->description, 80) }}</div>
                                    @endif
                                </td>
                                <td>{{ $department->entity?->name ?? '—' }}</td>
                                <td>{{ $department->clubs_count }}</td>
                                <td class="hide-mobile">{{ $department->memberships_count }}</td>
                                <td class="cell-actions">
                                    <a href="{{ route('orgchart.locate', ['department', $department->id]) }}" class="btn btn-secondary btn-icon btn-sm" title="Voir dans l'organigramme" aria-label="Voir dans l'organigramme"><x-admin.icon name="network" :size="15" /></a>
                                    <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-secondary btn-icon btn-sm" title="Modifier" aria-label="Modifier"><x-admin.icon name="edit" :size="15" /></a>
                                    <x-admin.delete-button :action="route('admin.departments.destroy', $department)" :message="'« '.$department->name.' » sera supprimé définitivement.'" title="Supprimer ce département ?" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $departments->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="layers" title="Aucun département" text="Aucun département ne correspond à ces critères." />
        @endif
    </article>
@endsection
