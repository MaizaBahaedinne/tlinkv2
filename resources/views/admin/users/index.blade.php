@extends('admin.layouts.app')

@section('title', 'Utilisateurs')
@section('section', 'Utilisateurs')

@section('content')
    <x-admin.page-header title="Utilisateurs" subtitle="Comptes de la plateforme, leurs postes dans l’organigramme et leur activité." />

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.users.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher par nom ou e-mail…">
                </div>
                <select name="access" aria-label="Niveau d'accès">
                    <option value="">Tous les niveaux d'accès</option>
                    @foreach($levels as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['access'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($users->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Postes</th>
                            <th class="hide-mobile">Adhésions</th>
                            <th class="hide-mobile">Publications</th>
                            <th class="hide-mobile">Inscrit le</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <x-admin.avatar :name="$user->name" :size="38" :src="$user->profile?->photoUrl()" />
                                        <div>
                                            <a href="{{ route('admin.users.show', $user) }}" class="cell-main">{{ $user->name }}</a>
                                            <div class="cell-sub">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @forelse(($user->orgPerson?->assignments ?? collect())->pluck('node.title')->filter()->unique() as $positionTitle)
                                        <span class="chip">{{ $positionTitle }}</span>
                                    @empty
                                        <span class="text-muted text-sm">Aucun</span>
                                    @endforelse
                                </td>
                                <td class="hide-mobile">{{ $user->memberships_count }}</td>
                                <td class="hide-mobile">{{ $user->posts_count }}</td>
                                <td class="hide-mobile text-sm text-muted nowrap">{{ $user->created_at?->format('d/m/Y') }}</td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary btn-sm">Voir la fiche</a>
                                    @if($canEdit)
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-sm">Modifier</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="user" title="Aucun utilisateur" text="Aucun utilisateur ne correspond à ces critères." />
        @endif
    </article>
@endsection
