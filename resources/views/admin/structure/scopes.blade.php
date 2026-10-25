@extends('admin.layouts.app')

@section('title', 'Périmètres')
@section('section', 'Périmètres')

@section('content')
    <x-admin.page-header title="Périmètres" subtitle="Gérez l’héritage entre périmètres (parent -> enfant). Les droits d’un parent couvrent ses enfants." />

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Hiérarchie des périmètres</h2>
                <p class="sub">Exemple: si University a pour parent Fondation, un responsable Fondation couvre aussi University.</p>
            </div>
        </div>

        <form method="post" action="{{ route('admin.scopes.update') }}" style="padding: 0 16px 16px;">
            @csrf
            @method('PUT')
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Périmètre</th>
                            <th>Parent (héritage)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scopes as $slug => $label)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $label }}</div>
                                    <div class="cell-sub">{{ $slug }}</div>
                                </td>
                                <td>
                                    <select name="parent[{{ $slug }}]" aria-label="Parent {{ $label }}">
                                        <option value="">Aucun (racine)</option>
                                        @foreach($scopes as $pSlug => $pLabel)
                                            <option value="{{ $pSlug }}" @selected(($hierarchy[$slug] ?? null) === $pSlug)>{{ $pLabel }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:flex-end; margin-top: 12px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </article>
@endsection
