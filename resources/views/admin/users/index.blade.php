@extends('admin.layouts.app')

@section('title', 'Utilisateurs')
@section('section', 'Utilisateurs')

@section('content')
    <x-admin.page-header title="Utilisateurs" subtitle="Comptes de la plateforme, leurs rôles actifs et leur activité." />

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.users.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher par nom ou e-mail…">
                </div>
                <select name="role" aria-label="Rôle">
                    <option value="">Tous les rôles</option>
                    @foreach($roles as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['role'] ?? '') === (string) $id)>{{ $name }}</option>
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
                            <th>Rôles actifs</th>
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
                                        <x-admin.avatar :name="$user->name" :size="38" />
                                        <div>
                                            <a href="{{ route('admin.users.show', $user) }}" class="cell-main">{{ $user->name }}</a>
                                            <div class="cell-sub">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @forelse($user->roleAssignments->pluck('role.name')->filter()->unique() as $roleName)
                                        <span class="chip">{{ $roleName }}</span>
                                    @empty
                                        <span class="text-muted text-sm">Aucun</span>
                                    @endforelse
                                </td>
                                <td class="hide-mobile">{{ $user->memberships_count }}</td>
                                <td class="hide-mobile">{{ $user->posts_count }}</td>
                                <td class="hide-mobile text-sm text-muted nowrap">{{ $user->created_at?->format('d/m/Y') }}</td>
                                <td class="cell-actions">
                                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary btn-sm">Voir la fiche</a>
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
