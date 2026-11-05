@extends('admin.layouts.app')

@section('title', 'Simulateur de rôles')
@section('section', 'Simulateur de rôles')

@section('content')
    <x-admin.page-header title="Simulateur de rôles" subtitle="Changez virtuellement de poste pour tester l’interface et les autorisations de son titulaire, sans modifier l’organigramme ni votre compte." />

    <div class="alert alert-info" role="note">
        <x-admin.icon name="shield" />
        <span>
            Pendant la simulation, vous n’avez <strong>que les droits du poste choisi</strong> : vos droits habituels sont suspendus. Un bandeau en bas de l’écran permet de <strong>quitter la simulation</strong> à tout moment.
            Les actions réalisées restent de vraies actions faites avec votre compte. Les postes d’administrateur global et de gestionnaire des postes et des droits ne sont pas simulables.
        </span>
    </div>

    @if($canManageAccess)
        <article class="card card-flush">
            <div class="card-head">
                <div>
                    <h2>Comptes autorisés</h2>
                    <p class="sub">Les administrateurs globaux ont toujours accès au simulateur. Autorisez ici d’autres comptes (testeurs) : ils pourront simuler n’importe quel poste simulable, <strong>même sans droit d’administration</strong>, et leurs actions resteront de vraies actions faites avec leur compte. Ils ne peuvent pas autoriser d’autres comptes.</p>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.simulator.access.store') }}" class="inline-form">
                    @csrf
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="E-mail du compte à autoriser" aria-label="E-mail du compte à autoriser" maxlength="190" required style="min-width:260px">
                    <button type="submit" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="16" /> Autoriser</button>
                </form>
                @error('email')<p class="error" style="margin-top:6px">{{ $message }}</p>@enderror
            </div>
            @if($allowedUsers->isNotEmpty())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Compte</th>
                                <th>Retirer l’accès</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($allowedUsers as $allowed)
                                <tr>
                                    <td>
                                        <div class="cell-user">
                                            <x-admin.avatar :name="$allowed->name" :size="34" />
                                            <div>
                                                <div class="cell-main">{{ $allowed->name }}</div>
                                                <div class="cell-sub">{{ $allowed->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('admin.simulator.access.destroy', $allowed) }}" class="inline-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm"><x-admin.icon name="trash" :size="16" /> Retirer</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body" style="border-top:1px solid var(--line)"><p class="text-sm text-muted" style="margin:0">Aucun compte autorisé pour le moment.</p></div>
            @endif
        </article>
    @endif

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.simulator.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Rechercher un poste…">
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Rechercher</button>
                @if($term !== '')
                    <a href="{{ route('admin.simulator.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($positions->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Poste</th>
                            <th class="hide-mobile">Périmètre</th>
                            <th>Niveau</th>
                            <th class="hide-mobile">Titulaires</th>
                            <th>Simuler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($positions as $node)
                            @php
                                [$levelLabel] = $levels[$node->access_level] ?? [$node->access_level];
                                $scope = $scopeMap[$node->id] ?? null;
                                $holders = $node->assignments->map(fn ($assignment) => $assignment->person?->full_name)->filter()->unique();
                            @endphp
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $node->title }}</div>
                                    <div class="cell-sub">{{ collect([$node->parent?->title, $node->subtitle])->filter()->implode(' · ') }}</div>
                                </td>
                                <td class="hide-mobile text-sm">{{ $scope !== null ? (\App\Support\OrgScopes::label($scope) ?? $scope) : 'Toute la plateforme' }}</td>
                                <td class="text-sm">{{ $levelLabel }}</td>
                                <td class="hide-mobile text-sm text-muted">{{ $holders->isEmpty() ? 'Vacant' : $holders->take(2)->implode(', ').($holders->count() > 2 ? ' +'.($holders->count() - 2) : '') }}</td>
                                <td>
                                    <form method="post" action="{{ route('admin.simulator.start', $node) }}" class="inline-form">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm"><x-admin.icon name="user-check" :size="16" /> Simuler</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $positions->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="key" title="Aucun poste simulable" text="Aucun poste ne correspond à cette recherche." />
        @endif
    </article>
@endsection
