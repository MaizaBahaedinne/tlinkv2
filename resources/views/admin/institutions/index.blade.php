@extends('admin.layouts.app')

@section('title', 'Institutions')
@section('section', 'Institutions')

@section('content')
    <x-admin.page-header title="Institutions" subtitle="Universités, lycées et autres établissements partenaires.">
        <x-slot:actions>
            <a href="{{ route('admin.institutions.create', array_filter(['entity' => $filters['entity'] ?? null])) }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvelle institution</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.institutions.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher une institution…">
                </div>
                <select name="type" aria-label="Type">
                    <option value="">Tous les types</option>
                    @foreach($types as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['type'] ?? '') === $code)>{{ $label }}</option>
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
                    <a href="{{ route('admin.institutions.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($institutions->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Institution</th>
                            <th>Type</th>
                            <th class="hide-mobile">Entité</th>
                            <th>Clubs</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($institutions as $institution)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $institution->name }}</div>
                                    @if($institution->description)
                                        <div class="cell-sub">{{ \Illuminate\Support\Str::limit($institution->description, 80) }}</div>
                                    @endif
                                </td>
                                <td>{{ $types[$institution->institution_type] ?? $institution->institution_type }}</td>
                                <td class="hide-mobile">{{ $institution->entity?->name ?? '—' }}</td>
                                <td>{{ $institution->clubs_count }}</td>
                                <td><x-admin.badge :status="$institution->is_active ? 'active' : 'inactive'" /></td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.institutions.edit', $institution) }}" class="btn btn-secondary btn-icon btn-sm" title="Modifier" aria-label="Modifier"><x-admin.icon name="edit" :size="15" /></a>
                                    <x-admin.delete-button :action="route('admin.institutions.destroy', $institution)" :message="'« '.$institution->name.' » sera supprimée définitivement.'" title="Supprimer cette institution ?" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $institutions->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="graduation" title="Aucune institution" text="Aucune institution ne correspond à ces critères." />
        @endif
    </article>
@endsection
