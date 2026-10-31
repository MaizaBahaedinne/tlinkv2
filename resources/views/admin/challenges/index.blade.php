@extends('admin.layouts.app')

@section('title', 'Défis')
@section('section', 'Défis')

@section('content')
    <x-admin.page-header title="Défis thématiques" subtitle="Lancez un défi : les clubs invités y proposent des activités, un comité les note, les résultats sont publiés à la date prévue.">
        <x-slot:actions>
            <a href="{{ route('admin.challenges.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouveau défi</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        @if($challenges->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Défi</th><th class="hide-mobile">Entité</th><th>Calendrier</th><th>Activités</th><th>Étape</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($challenges as $challenge)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.challenges.show', $challenge) }}" class="cell-main">{{ $challenge->title }}</a>
                                    <div class="cell-sub">{{ $challenge->theme }}</div>
                                </td>
                                <td class="hide-mobile">{{ \App\Support\OrgScopes::label($challenge->scope) ?? '—' }}</td>
                                <td class="nowrap text-sm">{{ $challenge->starts_at->format('d/m/Y') }} → {{ $challenge->ends_at->format('d/m/Y') }}<div class="cell-sub">Résultats le {{ $challenge->results_at->format('d/m/Y') }}</div></td>
                                <td>{{ $challenge->activities_count }}</td>
                                <td>@if($challenge->status === 'published')<span class="badge badge-info">{{ $challenge->phaseLabel() }}</span>@else<x-admin.badge :status="$challenge->status" />@endif</td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.challenges.show', $challenge) }}" class="btn btn-secondary btn-icon btn-sm" title="Détails" aria-label="Détails"><x-admin.icon name="eye" :size="15" /></a>
                                    <a href="{{ route('admin.challenges.edit', $challenge) }}" class="btn btn-secondary btn-icon btn-sm" title="Modifier" aria-label="Modifier"><x-admin.icon name="edit" :size="15" /></a>
                                    <x-admin.delete-button :action="route('admin.challenges.destroy', $challenge)" :message="'« '.$challenge->title.' » sera supprimé définitivement.'" title="Supprimer ce défi ?" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $challenges->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="award" title="Aucun défi" text="Créez le premier défi thématique de votre chapitre ou projet." />
        @endif
    </article>
@endsection
